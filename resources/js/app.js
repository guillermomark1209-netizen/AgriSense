import Alpine from 'alpinejs';
import { createIcons, Sprout, LayoutDashboard, Activity, Radio, BellRing, Sparkles, ChartNoAxesCombined, UserRound, ShieldCheck, Library, Files, Settings2, CircleHelp, LogOut, Leaf, Menu, Bell, CalendarDays, Plus, ArrowUpRight, ArrowRight, Thermometer, CloudRain, Droplets, FlaskConical, Sun, Moon, RefreshCw, BookOpenCheck, MapPin, TriangleAlert, BellOff, Send, Info, Trash2, ImageUp, X, Ellipsis } from 'lucide';
const icons={Sprout,LayoutDashboard,Activity,Radio,BellRing,Sparkles,ChartNoAxesCombined,UserRound,ShieldCheck,Library,Files,Settings2,CircleHelp,LogOut,Leaf,Menu,Bell,CalendarDays,Plus,ArrowUpRight,ArrowRight,Thermometer,CloudRain,Droplets,FlaskConical,Sun,Moon,RefreshCw,BookOpenCheck,MapPin,TriangleAlert,BellOff,Send,Info,Trash2,ImageUp,X,Ellipsis};
import Chart from 'chart.js/auto';
import { createClient } from '@supabase/supabase-js';
window.Alpine=Alpine;
Alpine.start();
createIcons({icons});
const themeColor=document.querySelector('meta[name="theme-color"]');
function setTheme(theme){
 document.documentElement.dataset.theme=theme;localStorage.setItem('agrisense-theme',theme);
 if(themeColor)themeColor.content=theme==='dark'?'#10261c':'#1B4332';
 for(const button of document.querySelectorAll('[data-theme-toggle]')){
  const isDark=theme==='dark';button.setAttribute('aria-pressed',String(isDark));button.setAttribute('aria-label',isDark?'Switch to light mode':'Switch to dark mode');
  const label=button.querySelector('[data-theme-toggle-label]');if(label)label.textContent=isDark?'Use light mode':'Use dark mode';
 }
}
setTheme(document.documentElement.dataset.theme||'light');
document.querySelectorAll('[data-theme-toggle]').forEach(button=>button.addEventListener('click',()=>setTheme(document.documentElement.dataset.theme==='dark'?'light':'dark')));
const base=document.querySelector('meta[name="agrisense-base"]')?.content||'/';
const owner=document.querySelector('meta[name="agrisense-user"]')?.content;
const connection=document.querySelector('[data-connection]');
function updateConnection(){if(connection){connection.textContent=navigator.onLine?'Network connected':'Offline Â· cached data only';connection.classList.toggle('offline',!navigator.onLine);}}
window.addEventListener('online',updateConnection);window.addEventListener('offline',updateConnection);updateConnection();
async function snapshot(mode,key,value){
 const db=await new Promise((resolve,reject)=>{const r=indexedDB.open('agrisense-offline',1);r.onupgradeneeded=()=>r.result.createObjectStore('snapshots');r.onsuccess=()=>resolve(r.result);r.onerror=()=>reject(r.error);});
 return new Promise((resolve,reject)=>{const tx=db.transaction('snapshots',mode==='get'?'readonly':'readwrite'),store=tx.objectStore('snapshots');const r=mode==='get'?store.get(key):mode==='clear'?store.clear():store.put(value,key);r.onsuccess=()=>resolve(r.result);r.onerror=()=>reject(r.error);tx.oncomplete=()=>db.close();});
}
async function clearSnapshots(){await snapshot('clear').catch(()=>{});localStorage.removeItem('agrisense-offline-owner');}
async function initializePrivacy(){
 if(owner&&localStorage.getItem('agrisense-offline-owner')&&localStorage.getItem('agrisense-offline-owner')!==owner){await clearSnapshots();localStorage.removeItem('agrisense-save-offline');}
}
const privacyReady=initializePrivacy();
const liveSensorGrid=document.querySelector('[data-sensor-live]');
let refreshLatestSensorReading=()=>{};
if(liveSensorGrid){let liveRequest=false,latestSensorDeviceId=null;const initialTimestamp=Date.parse(liveSensorGrid.querySelector('.sensor-time')?.dateTime||'');let latestReadingTimestamp=Number.isFinite(initialTimestamp)?initialTimestamp:Number.NEGATIVE_INFINITY;const sensorNames=['soil_temperature','air_temperature','air_humidity','light_percent','light_intensity','soil_moisture','soil_ph'];function formatReadingAge(timestamp){const seconds=Math.max(0,Math.floor((Date.now()-timestamp)/1000));const [amount,unit]=seconds<60?[seconds,'second']:seconds<3600?[Math.floor(seconds/60),'minute']:seconds<86400?[Math.floor(seconds/3600),'hour']:[Math.floor(seconds/86400),'day'];return `Updated ${new Intl.RelativeTimeFormat(undefined,{numeric:'auto'}).format(-amount,unit)}`;}async function refreshLatest(){if(liveRequest||document.hidden)return;liveRequest=true;const freshness=document.querySelector('[data-reading-freshness]');try{const response=await fetch(liveSensorGrid.dataset.sensorLive,{headers:{Accept:'application/json'},cache:'no-store',signal:AbortSignal.timeout(10000)});const data=await response.json().catch(()=>({}));if(!response.ok){if(freshness)freshness.textContent=data.upstream_status===401?'Supabase rejected the configured sensor secret key (401). Use a valid server secret key for this project URL.':data.upstream_status?`Supabase HTTP ${data.upstream_status}; check Data API permissions.`:data.message||'Supabase connection unavailable';return;}const reading=data.reading;if(latestSensorDeviceId!==data.device?.id){latestSensorDeviceId=data.device?.id;latestReadingTimestamp=Number.NEGATIVE_INFINITY;}if(!reading){for(const card of liveSensorGrid.querySelectorAll('.sensor-card')){const value=card.querySelector('.sensor-value'),time=card.querySelector('.sensor-time'),badge=card.querySelector('.status-badge');if(value)value.firstChild.textContent='—';if(time){time.dateTime='';time.textContent='Waiting for your device';}if(badge)badge.textContent='No reading';}}const observedAt=reading?.reading_at?Date.parse(reading.reading_at):NaN;if(reading&&Number.isFinite(observedAt)&&observedAt>=latestReadingTimestamp){latestReadingTimestamp=observedAt;for(const [index,name] of sensorNames.entries()){const card=liveSensorGrid.querySelectorAll('.sensor-card')[index],value=card?.querySelector('.sensor-value'),time=card?.querySelector('.sensor-time');if(!card||!value||!time)continue;const display=name==='air_temperature'?(reading.air_temperature??reading.temperature):name==='air_humidity'?(reading.air_humidity??reading.humidity):reading[name];value.firstChild.textContent=display==null?'—':Number(display).toFixed(1);time.dateTime=reading.reading_at;time.textContent=formatReadingAge(observedAt);const badge=card.querySelector('.status-badge');if(badge)badge.textContent=data.stale?'Stale reading':'Reading received';}}const pump=document.querySelector('[data-pump-status]'),update=document.querySelector('[data-sensor-update]'),activeDevices=document.querySelector('[data-active-devices]');if(pump)pump.textContent=reading?.pump==null?'No reading':reading.pump?'On':'Off';if(freshness)freshness.textContent=data.stale?'Stale reading from Supabase':reading?'Current reading from Supabase':'No reading available';if(update&&reading)update.textContent=`Last reading ${new Date(reading.reading_at).toLocaleString()}`;if(activeDevices)activeDevices.textContent=String(data.active_devices??0);}catch{if(freshness)freshness.textContent='Supabase request failed; check network and Laravel log.';}finally{liveRequest=false;}}refreshLatestSensorReading=refreshLatest;refreshLatest();const pollingTimer=window.setInterval(refreshLatest,Number(liveSensorGrid.dataset.refreshInterval)||10000);const updateAge=()=>{if(!Number.isFinite(latestReadingTimestamp))return;for(const time of liveSensorGrid.querySelectorAll('.sensor-time'))time.textContent=formatReadingAge(latestReadingTimestamp);};const ageTimer=window.setInterval(updateAge,1000);window.addEventListener('online',refreshLatest);window.addEventListener('pagehide',()=>{window.clearInterval(pollingTimer);window.clearInterval(ageTimer);},{once:true});document.addEventListener('visibilitychange',()=>{if(!document.hidden)refreshLatest();});}
document.querySelector('[data-logout]')?.addEventListener('submit',async e=>{e.preventDefault();await clearSnapshots();localStorage.removeItem('agrisense-save-offline');e.target.submit();});
const setting=document.querySelector('[data-offline-setting]');
if(setting){setting.checked=localStorage.getItem('agrisense-save-offline')===owner;setting.addEventListener('change',async()=>{if(setting.checked){localStorage.setItem('agrisense-save-offline',owner);document.querySelector('[data-settings-status]').textContent='Enabled. Open Monitoring to save a snapshot on this device.';}else{localStorage.removeItem('agrisense-save-offline');await clearSnapshots();document.querySelector('[data-settings-status]').textContent='Offline snapshots removed.';}});}
document.querySelector('[data-clear-snapshots]')?.addEventListener('click',async()=>{await clearSnapshots();document.querySelector('[data-settings-status]').textContent='Saved snapshots removed.';});
document.querySelectorAll('form[data-confirm]').forEach(form=>form.addEventListener('submit',e=>{if(!confirm(form.dataset.confirm))e.preventDefault();}));
for(const input of document.querySelectorAll('[data-image-input]')){let url;input.addEventListener('change',()=>{const form=input.closest('form'),preview=form?.querySelector('[data-image-preview]');if(url)URL.revokeObjectURL(url);if(!preview)return;const file=input.files?.[0];preview.hidden=!file;if(file){url=URL.createObjectURL(file);preview.src=url;if(input.hasAttribute('data-auto-submit'))form.requestSubmit();}});}
document.querySelectorAll('form[data-loading-label]').forEach(form=>form.addEventListener('submit',()=>{const button=form.querySelector('button[type="submit"],button:not([type])');if(button)button.disabled=true;form.querySelector('[data-form-status]').textContent=form.dataset.loadingLabel;form.setAttribute('aria-busy','true');}));
const refreshers=[];
for(const card of document.querySelectorAll('[data-monitor]')){
 const form=card.querySelector('[data-chart-filters]'),state=card.querySelector('[data-chart-state]'),stats=card.querySelector('[data-chart-stats]');let busy=false;
 const chart=new Chart(card.querySelector('canvas'),{type:'line',data:{labels:[],datasets:[]},options:{responsive:true,maintainAspectRatio:false,animation:false,interaction:{mode:'index',intersect:false},plugins:{legend:{labels:{usePointStyle:true,boxWidth:5,font:{size:10}}}},scales:{x:{grid:{display:false},ticks:{maxTicksLimit:6,font:{size:10}}},y:{grid:{color:'#edf0e8'},ticks:{font:{size:10}}}}}});
 function draw(data,cached){
  const groups=[...new Set(data.points.map(p=>p.device_id))],times=[...new Set(data.points.map(p=>p.time))].sort(),colors=['#40916c','#a17c47','#a0a73d','#689aa0','#b97d70'];
  chart.data.labels=times.map(t=>new Date(t).toLocaleString([],{month:'short',day:'numeric',hour:'2-digit',minute:'2-digit'}));
  chart.data.datasets=groups.map((device,i)=>{const values=new Map(data.points.filter(p=>p.device_id===device).map(p=>[p.time,p.value]));return{label:'Device '+device,data:times.map(t=>values.get(t)??null),borderColor:colors[i%colors.length],borderWidth:2,pointRadius:times.length<20?2:0,spanGaps:true,tension:.2};});chart.update();stats.replaceChildren();
  for(const [label,value] of [['Minimum',data.statistics.minimum],['Average',data.statistics.average],['Maximum',data.statistics.maximum],['Latest observed',data.latest?.[form.elements.sensor.value]]]){const node=document.createElement('div'),strong=document.createElement('strong');node.textContent=label;strong.textContent=value==null?'â€”':Number(value).toFixed(1);node.append(strong);stats.append(node);}
  const synced=new Date(data.synced_at).toLocaleString();state.classList.remove('error');
  state.textContent=cached?'Offline snapshot Â· NOT LIVE Â· last synchronized '+synced:!data.points.length?'No readings in this period. Connect a device or change filters.':(data.stale?'Latest reading is stale. ':'Readings loaded. ')+(data.limited?'Showing the most recent 2,000 readings. ':'')+'Synchronized '+synced;
 }
 async function refresh(){
  await privacyReady;if(busy||document.hidden)return;
  if(form.elements.range.value==='custom'&&(!form.elements.from.value||!form.elements.to.value)){state.textContent='Choose both dates to load a custom range.';return;}
  busy=true;const params=new URLSearchParams(new FormData(form));for(const[k,v]of[...params])if(!v)params.delete(k);const key=owner+':'+params.toString();card.setAttribute('aria-busy','true');state.textContent='Loading sensor historyâ€¦';
  try{const response=await fetch(card.dataset.endpoint+'?'+params,{headers:{Accept:'application/json'},cache:'no-store',signal:AbortSignal.timeout(15000)});if(!response.ok){const error=await response.json().catch(()=>({}));state.textContent=response.status===401?'Session expired. Sign in again.':error.message||'Readings could not be loaded.';state.classList.add('error');chart.data.datasets=[];chart.update();stats.replaceChildren();return;}
   const data=await response.json();draw(data,false);
   if(localStorage.getItem('agrisense-save-offline')===owner){localStorage.setItem('agrisense-offline-owner',owner);await snapshot('put',key,data).catch(()=>{});await snapshot('put','latest',{...data,sensor:form.elements.sensor.selectedOptions[0].textContent}).catch(()=>{});}
  }catch{const cached=await snapshot('get',key).catch(()=>null);if(cached&&localStorage.getItem('agrisense-save-offline')===owner)draw(cached,true);else{state.textContent='Connection unavailable. Any chart still shown is from a previous load, NOT LIVE. No saved snapshot matches these filters.';state.classList.add('error');}}
  finally{busy=false;card.removeAttribute('aria-busy');}
 }
 form.addEventListener('submit',e=>{e.preventDefault();refresh();});form.addEventListener('change',()=>{card.querySelectorAll('[data-custom]').forEach(el=>el.hidden=form.elements.range.value!=='custom');refresh();});
 refreshers.push(refresh);refresh();setInterval(refresh,30000);window.addEventListener('online',refresh);window.addEventListener('offline',()=>{state.textContent='Offline Â· chart shows a previous load, NOT LIVE.';});
}
if(owner&&(refreshers.length||liveSensorGrid)){let client;async function connect(){try{const response=await fetch(base+'realtime/credentials',{headers:{Accept:'application/json'},cache:'no-store'});if(!response.ok)return;const data=await response.json();if(!client){client=createClient(data.url,data.key,{auth:{persistSession:false,autoRefreshToken:false,detectSessionInUrl:false}});await client.realtime.setAuth(data.token);client.channel(data.channel,{config:{private:true}}).on('broadcast',{event:'reading'},()=>{refreshLatestSensorReading();refreshers.forEach(refresh=>refresh());}).subscribe();}else await client.realtime.setAuth(data.token);}catch{/* Authenticated polling continues during Realtime outages. */}}connect();const realtimeRefreshTimer=window.setInterval(connect,240000);window.addEventListener('pagehide',()=>window.clearInterval(realtimeRefreshTimer),{once:true});}
const devicePage = document.querySelector('[data-device-page]');
if (devicePage) {
 const list = devicePage.querySelector('[data-device-list]');
 const count = devicePage.querySelector('[data-device-count]');
 const status = devicePage.querySelector('[data-device-list-status]');
 const addButton = devicePage.querySelector('[data-add-device]');
 const dialog = devicePage.querySelector('[data-add-device-dialog]');
 const addForm = dialog.querySelector('[data-add-device-form]');
 const cancelButton = dialog.querySelector('[data-cancel-add-device]');
 const submitButton = addForm.querySelector('button[type="submit"]');
 const addError = dialog.querySelector('[data-add-device-error]');
 const addStatus = dialog.querySelector('[data-add-device-status]');
 const success = dialog.querySelector('[data-add-device-success]');
 let adding = false, added = false, pendingRefresh = false;
 let request, client, channels = [], pollTimer, tokenTimer, generation = 0, running = false, loading = false, connecting = false, realtimeUnavailable = false;
 async function refreshDevices(force = false) {
  if (loading) {
   if (force === true) { pendingRefresh = true; request?.abort(); }
   return;
  }
  if (!running || document.hidden || (force !== true && list.contains(document.activeElement))) { return; }
  loading = true;
  const currentGeneration = generation;
  const currentRequest = new AbortController();
  request = currentRequest;
  devicePage.setAttribute('aria-busy', 'true');
  status.textContent = 'Loading devices…';
  try {
   const response = await fetch(devicePage.dataset.endpoint, { headers: { Accept: 'application/json' }, cache: 'no-store', signal: AbortSignal.any([currentRequest.signal, AbortSignal.timeout(20000)]) });
   const data = await response.json().catch(() => ({}));
   if (!response.ok) {
    throw new Error(response.status === 401 || response.status === 419 ? 'Your session expired. Sign in again to view your devices.' : response.status === 403 ? 'You do not have permission to view these devices.' : data.message || 'Devices could not be loaded. Reload the page to try again.');
   }
   if (typeof data.html !== 'string' || typeof data.total !== 'number') { throw new Error('The device list could not be confirmed. Refresh the page and sign in again.'); }
   if (!running || currentGeneration !== generation || (force !== true && list.contains(document.activeElement))) { return; }
   list.innerHTML = data.html;
   count.textContent = `${data.total} devices available to you`;
   createIcons({ icons });
   status.textContent = realtimeUnavailable ? 'Live notifications are unavailable; automatic refresh continues.' : '';
  } catch (error) {
   if (running && currentGeneration === generation && !currentRequest.signal.aborted) {
    status.textContent = error instanceof TypeError || error.name === 'TimeoutError' ? 'Network error loading devices. Previously displayed data may be outdated. Automatic loading will retry.' : error.message;
   }
  } finally {
   if (currentGeneration === generation) {
    loading = false;
    if (status.textContent === 'Loading devices…') { status.textContent = ''; }
    devicePage.removeAttribute('aria-busy');
    if (pendingRefresh && running) { pendingRefresh = false; refreshDevices(true); }
   }
  }
 }
 async function connectDeviceUpdates() {
  if (!running || connecting) { return; }
  const currentGeneration = generation;
  connecting = true;
  try {
   const response = await fetch(devicePage.dataset.realtimeEndpoint, { headers: { Accept: 'application/json' }, cache: 'no-store', signal: AbortSignal.timeout(10000) });
   if (response.status === 404) { return; }
   if (!response.ok) { throw new Error('Live notifications unavailable'); }
   const credentials = await response.json();
   if (!running || currentGeneration !== generation) { return; }
   if (!client) {
    client = createClient(credentials.url, credentials.key, { auth: { persistSession: false, autoRefreshToken: false, detectSessionInUrl: false } });
   }
   await client.realtime.setAuth(credentials.token);
   if (!running || currentGeneration !== generation) { return; }
   if (!channels.length) {
    for (const [name, event] of [[credentials.catalog_channel, 'catalog'], [credentials.channel, 'reading']]) {
     if (!name) { continue; }
     const channel = client.channel(name, { config: { private: true } }).on('broadcast', { event }, refreshDevices);
     channels.push(channel);
     channel.subscribe(state => {
      if (!running || currentGeneration !== generation) { return; }
      realtimeUnavailable = channels.some(subscription => subscription.state !== 'joined');
      if (state === 'CHANNEL_ERROR' || state === 'TIMED_OUT' || state === 'CLOSED') {
       status.textContent = 'Live notifications are unavailable; automatic refresh continues.';
      } else if (state === 'SUBSCRIBED') { refreshDevices(); }
     });
    }
   }
  } catch {
   if (running && currentGeneration === generation) { realtimeUnavailable = true; status.textContent = 'Live notifications are unavailable; automatic refresh continues.'; }
  } finally { if (currentGeneration === generation) { connecting = false; } }
 }
 function startDeviceUpdates() {
  if (running) { return; }
  running = true;
  refreshDevices();
  connectDeviceUpdates();
  pollTimer = window.setInterval(refreshDevices, 30000);
  tokenTimer = window.setInterval(connectDeviceUpdates, 240000);
 }
 function stopDeviceUpdates() {
  running = false;
  generation++;
  loading = false;
  connecting = false;
  request?.abort();
  window.clearInterval(pollTimer);
  window.clearInterval(tokenTimer);
  if (client) {
   client.removeAllChannels().catch(() => {});
   client.realtime.disconnect().catch(() => {});
   client = null;
  }
  channels = [];
 }
 addButton.addEventListener('click', () => {
  addForm.reset();
  for (const field of addForm.querySelectorAll('input, select, textarea')) { field.disabled = false; }
  added = false;
  success.classList.add('hidden');
  dialog.querySelector('[data-add-device-token]').textContent = '';
  addError.textContent = '';
  addStatus.textContent = '';
  submitButton.disabled = false;
  cancelButton.textContent = 'Cancel';
  dialog.showModal();
 });
 cancelButton.addEventListener('click', () => { if (!adding) { dialog.close(); } });
 dialog.addEventListener('cancel', event => { if (adding) { event.preventDefault(); } });
 dialog.addEventListener('close', () => { dialog.querySelector('[data-add-device-token]').textContent = ''; });
 addForm.addEventListener('submit', async event => {
  event.preventDefault();
  if (adding || added) { return; }
  adding = true;
  submitButton.disabled = true;
  cancelButton.disabled = true;
  addForm.setAttribute('aria-busy', 'true');
  addError.textContent = '';
  addStatus.textContent = 'Adding device…';
  const body = new FormData(addForm);
  for (const field of addForm.querySelectorAll('input, select, textarea')) { field.disabled = true; }
  try {
   const response = await fetch(addForm.action, { method: 'POST', body, headers: { Accept: 'application/json' }, signal: AbortSignal.timeout(20000) });
   const data = await response.json().catch(() => ({}));
   if (!response.ok) {
    throw new Error(response.status === 401 || response.status === 419 ? 'Your session expired. Reload the page and sign in again.' : response.status === 403 ? 'Device registration is not permitted.' : response.status === 429 ? 'Too many attempts. Wait a minute before trying again.' : Object.values(data.errors || {}).flat().join(' ') || data.message || 'Device could not be added. Please try again.');
   }
   if (!data.id || !data.device_token) { throw new Error('Registration could not be confirmed. Check your device list before retrying.'); }
   added = true;
   dialog.querySelector('[data-add-device-message]').textContent = data.message;
   dialog.querySelector('[data-add-device-token]').textContent = data.device_token;
   success.classList.remove('hidden');
   cancelButton.textContent = 'Done';
   const listUrl = new URL(devicePage.dataset.endpoint, window.location.origin);
   listUrl.searchParams.delete('page');
   devicePage.dataset.endpoint = listUrl.href;
   const pageUrl = new URL(window.location.href);
   pageUrl.searchParams.delete('page');
   window.history.replaceState(null, '', pageUrl);
   refreshDevices(true);
  } catch (error) {
   addError.textContent = error instanceof TypeError || error.name === 'TimeoutError' ? 'Network error. Check the device list before retrying; registration may have completed.' : error.message;
   refreshDevices(true);
  } finally {
   adding = false;
   for (const field of addForm.querySelectorAll('input, select, textarea')) { field.disabled = added; }
   submitButton.disabled = added;
   cancelButton.disabled = false;
   addStatus.textContent = '';
   addForm.removeAttribute('aria-busy');
  }
 });
 window.addEventListener('online', () => { refreshDevices(); connectDeviceUpdates(); });
 document.addEventListener('visibilitychange', () => { if (!document.hidden) { refreshDevices(); connectDeviceUpdates(); } });
 window.addEventListener('pagehide', stopDeviceUpdates);
 window.addEventListener('pageshow', event => { if (event.persisted) { startDeviceUpdates(); } });
 startDeviceUpdates();
}
const deviceForm = document.querySelector('[data-device-form]');
if (deviceForm) {
 const ownerSelect = deviceForm.querySelector('[name="user_id"]');
 const cropSelect = deviceForm.querySelector('[name="crop_id"]');
 const status = deviceForm.querySelector('[data-form-status]');
 const saveButton = deviceForm.querySelector('button[type="submit"]');
 let cropRequest, saving = false;
 ownerSelect?.addEventListener('change', async () => {
  if (saving) { return; }
  cropRequest?.abort();
  cropRequest = new AbortController();
  const currentRequest = cropRequest;
  cropSelect.replaceChildren(new Option('Unassigned', ''));
  if (!ownerSelect.value) { cropSelect.disabled = false; status.textContent = ''; return; }
  cropSelect.disabled = true;
  status.textContent = 'Loading crops…';
  try {
   const url = new URL(deviceForm.dataset.cropsEndpoint, window.location.origin);
   url.searchParams.set('user_id', ownerSelect.value);
   const response = await fetch(url, { headers: { Accept: 'application/json' }, cache: 'no-store', signal: AbortSignal.any([currentRequest.signal, AbortSignal.timeout(12000)]) });
   if (!response.ok) { throw new Error(response.status === 403 ? 'You do not have permission to load these crops.' : 'Crops could not be loaded. Choose the owner again to retry, or save as Unassigned.'); }
   const data = await response.json();
   for (const crop of data.crops) { cropSelect.add(new Option(`${crop.name} · ${crop.location || ''}`, crop.id)); }
   status.textContent = '';
  } catch (error) {
   if (!currentRequest.signal.aborted) { status.textContent = error instanceof TypeError || error.name === 'TimeoutError' ? 'Network error loading crops. Choose the owner again to retry, or save as Unassigned.' : error.message; }
  } finally {
   if (cropRequest === currentRequest && !saving) { cropSelect.disabled = false; }
  }
 });
 if (ownerSelect) { deviceForm.addEventListener('submit', async event => {
  event.preventDefault();
  if (saving) { return; }
  saving = true;
  saveButton.disabled = true;
  deviceForm.setAttribute('aria-busy', 'true');
  status.textContent = 'Registering device…';
  const body = new FormData(deviceForm);
  cropRequest?.abort();
  ownerSelect.disabled = true;
  cropSelect.disabled = true;
  try {
   const response = await fetch(deviceForm.action, { method: 'POST', body, headers: { Accept: 'application/json' } });
   const data = await response.json().catch(() => ({}));
   if (!response.ok) {
    throw new Error(response.status === 403 ? 'You do not have permission to register devices.' : response.status === 419 || response.status === 401 ? 'Your session expired. Refresh the page and sign in again.' : Object.values(data.errors || {}).flat().join(' ') || data.message || 'Registration failed. Please try again.');
   }
   if (!data.redirect) { throw new Error('Registration could not be confirmed. Check the device list before retrying.'); }
   window.location.assign(data.redirect);
  } catch (error) {
   status.textContent = error instanceof TypeError ? 'Network error. Check the device list before retrying; the device may have been registered.' : error.message;
   saving = false;
   ownerSelect.disabled = false;
   cropSelect.disabled = false;
   saveButton.disabled = false;
   deviceForm.removeAttribute('aria-busy');
  }
 }); }
}
if('serviceWorker'in navigator&&window.isSecureContext)navigator.serviceWorker.register(base+'sw.js',{scope:base}).catch(()=>{});
const aiChat=document.querySelector('[data-ai-chat]');
if(aiChat){
 const messages=aiChat.querySelector('[data-ai-messages]'),form=aiChat.querySelector('[data-ai-form]'),input=aiChat.querySelector('[data-ai-input]'),send=aiChat.querySelector('[data-ai-send]'),status=aiChat.querySelector('[data-ai-status]'),imageInput=aiChat.querySelector('[data-ai-image-input]'),upload=aiChat.querySelector('[data-ai-upload]'),preview=aiChat.querySelector('[data-ai-image-preview]'),previewImage=aiChat.querySelector('[data-ai-image-preview-image]'),removeImage=aiChat.querySelector('[data-ai-remove-image]');
 const welcome='Hello! I’m your AgriSense AI Assistant.';const historyData=aiChat.querySelector('[data-ai-history]');let history=[];
 function add(role,content,imageUrl=null){const item=document.createElement('article'),label=document.createElement('span'),text=document.createElement('p');item.className='assistant-message '+(role==='user'?'user-message':'');label.className='section-kicker';label.textContent=role==='user'?'YOU':'AGRISENSE ASSISTANT';text.textContent=content;item.append(label);if(imageUrl){const image=document.createElement('img');image.className='assistant-message-image';image.src=imageUrl;image.alt='Uploaded crop photo';item.append(image);}item.append(text);messages.append(item);messages.scrollTop=messages.scrollHeight;}
 function addSources(sources){if(!Array.isArray(sources))return;const item=document.createElement('article'),label=document.createElement('span'),list=document.createElement('ul'),seen=new Set;item.className='assistant-sources';label.className='section-kicker';label.textContent='SOURCES';for(const source of sources){const title=typeof source?.title==='string'?source.title.trim():'';if(!title)continue;let url='';try{const parsed=new URL(typeof source.url==='string'?source.url:'');if(['http:','https:'].includes(parsed.protocol))url=parsed.href;}catch{}const key=title+'|'+url;if(seen.has(key))continue;seen.add(key);const entry=document.createElement('li');if(url){const link=document.createElement('a');link.href=url;link.target='_blank';link.rel='noopener noreferrer';link.textContent=title;entry.append(link);}else entry.textContent=title;list.append(entry);}if(!list.children.length)return;item.append(label,list);messages.append(item);messages.scrollTop=messages.scrollHeight;}
 function reset(){history=[];messages.replaceChildren();add('assistant',welcome);status.textContent='';input.focus();}
 function loadHistory(){let saved=[];try{saved=JSON.parse(historyData?.textContent||'[]');}catch{}messages.replaceChildren();history=[];for(const message of saved){if(!['user','assistant'].includes(message?.role)||typeof message.content!=='string')continue;add(message.role,message.content,message.image_url);history.push({role:message.role,content:message.content});if(message.role==='assistant')addSources(message.sources);}if(!history.length)add('assistant',welcome);}
 loadHistory();aiChat.querySelector('[data-ai-clear]').addEventListener('click',async()=>{try{const response=await fetch(aiChat.dataset.clearEndpoint,{method:'DELETE',headers:{'Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content||''}});if(!response.ok)throw new Error();reset();}catch{status.textContent='Chat could not be cleared. Please try again.';}});
 let previewUrl;function clearImage(){if(previewUrl)URL.revokeObjectURL(previewUrl);previewUrl=undefined;imageInput.value='';preview.hidden=true;previewImage.removeAttribute('src');}upload.addEventListener('click',()=>imageInput.click());imageInput.addEventListener('change',()=>{const file=imageInput.files?.[0];if(!file)return;if(!['image/jpeg','image/png','image/webp'].includes(file.type)){clearImage();status.textContent='Choose a JPG, PNG, or WebP image.';return;}if(file.size>10*1024*1024){clearImage();status.textContent='Choose an image up to 10 MB.';return;}if(previewUrl)URL.revokeObjectURL(previewUrl);previewUrl=URL.createObjectURL(file);previewImage.src=previewUrl;preview.hidden=false;status.textContent='';});removeImage.addEventListener('click',clearImage);
 form.addEventListener('submit',async event=>{event.preventDefault();const message=input.value.trim(),image=imageInput.files?.[0];if(!message&&!image)return;const question=message||'Please identify and analyze this crop image.',priorHistory=history.slice(-12),body=new FormData();add('user',question,image?previewUrl:null);history.push({role:'user',content:question});body.append('message',message);body.append('conversation_id',aiChat.dataset.conversationId);body.append('crop_id',aiChat.dataset.cropId||'');for(const [index,entry] of priorHistory.entries()){body.append(`history[${index}][role]`,entry.role);body.append(`history[${index}][content]`,entry.content);}if(image)body.append('image',image);input.value='';input.disabled=true;send.disabled=true;upload.disabled=true;status.textContent='Thinking…';
  try{const response=await fetch(aiChat.dataset.endpoint,{method:'POST',headers:{'Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content||''},body});const data=await response.json().catch(()=>({}));if(!response.ok)throw new Error(data.message||'AI Assistant is currently unavailable. Please try again later.');add('assistant',data.message);addSources(data.sources);history.push({role:'assistant',content:data.message});clearImage();status.textContent='';}catch(error){status.textContent=error instanceof Error?error.message:'AI Assistant is currently unavailable. Please try again later.';}finally{input.disabled=false;send.disabled=false;upload.disabled=false;input.focus();}
 });
}
document.querySelector('[data-ai-new-chat]')?.addEventListener('click',async event=>{const button=event.currentTarget;try{button.disabled=true;const response=await fetch(button.dataset.endpoint,{method:'POST',headers:{'Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content||''}});const data=await response.json().catch(()=>({}));if(!response.ok||!data.url)throw new Error();window.location.assign(data.url);}catch{button.disabled=false;}});
const deleteDialog=document.querySelector('[data-ai-delete-dialog]');let conversationToDelete;document.querySelectorAll('[data-ai-delete-conversation]').forEach(button=>button.addEventListener('click',()=>{conversationToDelete={endpoint:button.dataset.endpoint,current:button.dataset.current==='true'};deleteDialog?.showModal();}));deleteDialog?.addEventListener('close',async()=>{if(deleteDialog.returnValue!=='delete'||!conversationToDelete)return;const target=conversationToDelete;conversationToDelete=undefined;try{const response=await fetch(target.endpoint,{method:'DELETE',headers:{'Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content||''}});if(!response.ok)throw new Error();if(target.current){const newChat=document.querySelector('[data-ai-new-chat]');const created=await fetch(newChat.dataset.endpoint,{method:'POST',headers:{'Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content||''}});const data=await created.json().catch(()=>({}));if(!created.ok||!data.url)throw new Error();window.location.assign(data.url);return;}window.location.reload();}catch{window.location.reload();}});
let installPrompt;const install=document.querySelector('[data-install]');window.addEventListener('beforeinstallprompt',e=>{e.preventDefault();installPrompt=e;if(install)install.hidden=false;});install?.addEventListener('click',async()=>{if(!installPrompt)return;await installPrompt.prompt();installPrompt=null;install.hidden=true;});
