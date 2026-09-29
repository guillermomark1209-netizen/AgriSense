#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <HTTPClient.h>
#include <LittleFS.h>
#include <ArduinoJson.h>
#include <DHT.h>
#include <time.h>
#include <esp_system.h>
#include "config.h"

DHT dht(DHT_PIN, DHT_TYPE);
unsigned long lastSample = 0;
unsigned long lastUpload = 0;
const size_t MAX_QUEUED = 2000;

String uuidV4() {
  uint8_t bytes[16];
  esp_fill_random(bytes, sizeof(bytes));
  bytes[6] = (bytes[6] & 0x0f) | 0x40;
  bytes[8] = (bytes[8] & 0x3f) | 0x80;
  char value[37];
  snprintf(value, sizeof(value), "%02x%02x%02x%02x-%02x%02x-%02x%02x-%02x%02x-%02x%02x%02x%02x%02x%02x",
    bytes[0],bytes[1],bytes[2],bytes[3],bytes[4],bytes[5],bytes[6],bytes[7],bytes[8],bytes[9],bytes[10],bytes[11],bytes[12],bytes[13],bytes[14],bytes[15]);
  return String(value);
}

void queueObservation() {
  time_t epoch = time(nullptr);
  if (epoch < 1700000000) {
    Serial.println("Waiting for a valid clock; no timestamp will be invented.");
    return;
  }
  size_t count = 0;
  File folder = LittleFS.open("/queue");
  for (File item = folder.openNextFile(); item; item = folder.openNextFile()) {
    count++;
    item.close();
  }
  folder.close();
  if (count >= MAX_QUEUED || LittleFS.totalBytes() - LittleFS.usedBytes() < 4096) {
    Serial.println("Queue full; observation not recorded. Restore connectivity.");
    return;
  }
  float temperature = dht.readTemperature();
  float humidity = dht.readHumidity();
  if (isnan(temperature) && isnan(humidity)) {
    Serial.println("DHT sensor returned no valid measurements.");
    return;
  }
  struct tm utc;
  gmtime_r(&epoch, &utc);
  char observed[25];
  strftime(observed, sizeof(observed), "%Y-%m-%dT%H:%M:%SZ", &utc);
  JsonDocument reading;
  String id = uuidV4();
  reading["device_id"] = DEVICE_ID;
  reading["reading_id"] = id;
  reading["reading_at"] = observed;
  if (!isnan(temperature)) reading["temperature"] = temperature;
  if (!isnan(humidity)) reading["humidity"] = humidity;
  // Add soil_moisture (0-100%), soil_ph (0-14), and light_intensity (lux)
  // only after implementing real, calibrated drivers for your selected sensors.
  // Omitted channels remain null; they are never replaced with fake readings.
  String temporary = "/queue/" + id + ".tmp";
  String target = "/queue/" + id + ".json";
  File file = LittleFS.open(temporary, "w");
  if (!file) { Serial.println("Queue storage failed."); return; }
  size_t expected = measureJson(reading);
  size_t written = serializeJson(reading, file);
  file.flush();
  file.close();
  if (written == expected) {
    if (!LittleFS.rename(temporary,target)) Serial.println("Queue commit failed.");
  } else {
    LittleFS.remove(temporary);
    Serial.println("Incomplete write; observation not queued.");
  }
}

void uploadOne() {
  if (WiFi.status() != WL_CONNECTED) return;
  File folder = LittleFS.open("/queue");
  File file = folder.openNextFile();
  while (file && !String(file.name()).endsWith(".json")) {
    file.close();
    file = folder.openNextFile();
  }
  if (!file) { folder.close(); return; }
  String path = file.path();
  String payload = file.readString();
  file.close();
  folder.close();
  WiFiClientSecure tls;
  tls.setCACert(ROOT_CA);
  HTTPClient http;
  http.setTimeout(15000);
  if (!http.begin(tls, READING_URL)) return;
  http.addHeader("Content-Type", "application/json");
  http.addHeader("Accept", "application/json");
  http.addHeader("Authorization", String("Bearer ") + DEVICE_TOKEN);
  int status = http.POST(payload);
  if (status == 200 || status == 201) {
    JsonDocument response;
    if (!deserializeJson(response,http.getString())) {
      JsonDocument original;
      if (!deserializeJson(original,payload) &&
          String(response["reading_id"].as<const char*>()) == String(original["reading_id"].as<const char*>())) {
        LittleFS.remove(path);
      }
    }
  } else if (status == 422 || status == 409) {
    // Retain rejected records for inspection; stop retrying a permanently invalid record.
    String destination = "/rejected/" + path.substring(path.lastIndexOf('/')+1);
    LittleFS.rename(path,destination);
    Serial.println("Reading rejected; retained for inspection.");
  } else {
    Serial.printf("Upload pending (HTTP %d). Credentials are never printed.\n",status);
  }
  http.end();
}

void setup() {
  Serial.begin(115200);
  dht.begin();
  // Do not automatically format existing storage and erase queued readings.
  if (!LittleFS.begin(false)) {
    Serial.println("Initialize a LittleFS partition before first use.");
    while (true) delay(1000);
  }
  LittleFS.mkdir("/queue");
  LittleFS.mkdir("/rejected");
  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  WiFi.setAutoReconnect(true);
  configTime(0,0,"pool.ntp.org","time.nist.gov");
}
void loop() {
  unsigned long tick = millis();
  if (tick - lastSample >= SAMPLE_INTERVAL_MS) { lastSample = tick; queueObservation(); }
  if (tick - lastUpload >= 3000) { lastUpload = tick; uploadOne(); }
  delay(20);
}
