-- Run AFTER Laravel migrations in your Supabase SQL editor.
-- Laravel uses its server-only PostgreSQL connection. Browser clients never
-- receive database credentials or the service-role key.
BEGIN;
INSERT INTO storage.buckets (id,name,public,file_size_limit,allowed_mime_types) VALUES
('crop-images','crop-images',false,5242880,ARRAY['image/jpeg','image/png','image/webp']),
('plant-analysis','plant-analysis',false,5242880,ARRAY['image/jpeg','image/png','image/webp']),
('profile-images','profile-images',false,5242880,ARRAY['image/jpeg','image/png','image/webp']),
('knowledge-documents','knowledge-documents',false,5242880,ARRAY['application/pdf','text/plain'])
ON CONFLICT (id) DO UPDATE SET public=false,file_size_limit=EXCLUDED.file_size_limit,allowed_mime_types=EXCLUDED.allowed_mime_types;

-- No public Storage policies: all objects are accessed via Laravel authorization
-- and short-lived signed URLs. Do not add public read policies to these buckets.
-- Deny direct browser access even if Supabase default grants are configured.
REVOKE ALL ON TABLE public.users,public.profiles,public.user_roles,public.crops,
public.devices,public.sensor_readings,public.crop_sensor_thresholds,public.alerts,
public.sources,public.documents,public.document_chunks,public.ai_conversations,
public.ai_messages,public.ai_message_sources,public.ai_audit_logs,public.crop_events,
public.audit_logs,public.system_settings,public.sessions,public.password_reset_tokens,
public.cache,public.cache_locks,public.jobs,public.job_batches,public.failed_jobs
FROM anon, authenticated;

-- Private Realtime carries only an invalidation event, never private row data.
DROP POLICY IF EXISTS agrisense_private_broadcast ON realtime.messages;
CREATE POLICY agrisense_private_broadcast ON realtime.messages
FOR SELECT TO authenticated
USING (
    extension = 'broadcast'
    AND realtime.topic() = 'farm:' || (auth.jwt()->>'agrisense_user_id')
);
CREATE OR REPLACE FUNCTION public.agrisense_notify_reading()
RETURNS trigger LANGUAGE plpgsql SECURITY DEFINER SET search_path = '' AS $$
DECLARE owner_id bigint;
BEGIN
    SELECT user_id INTO owner_id FROM public.devices WHERE id=NEW.device_id;
    IF owner_id IS NOT NULL THEN
        PERFORM realtime.send(jsonb_build_object('changed',true),'reading','farm:' || owner_id::text,true);
    END IF;
    RETURN NEW;
END;
$$;
REVOKE ALL ON FUNCTION public.agrisense_notify_reading() FROM PUBLIC;
DROP TRIGGER IF EXISTS agrisense_reading_changed ON public.sensor_readings;
CREATE TRIGGER agrisense_reading_changed AFTER INSERT OR UPDATE ON public.sensor_readings
FOR EACH ROW EXECUTE FUNCTION public.agrisense_notify_reading();
COMMIT;
