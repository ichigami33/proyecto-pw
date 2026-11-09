-- Contexto de la cuenta en una sola consulta. Solo devuelve datos de auth.uid().
CREATE OR REPLACE FUNCTION powerbull_private.contexto_usuario()
RETURNS jsonb
LANGUAGE plpgsql STABLE SECURITY DEFINER SET search_path = ''
AS $$
DECLARE
  v_uid uuid := auth.uid();
  v_result jsonb;
BEGIN
  IF v_uid IS NULL THEN
    RAISE EXCEPTION 'Iniciá sesión para continuar.' USING ERRCODE = '28000';
  END IF;
  SELECT jsonb_build_object(
    'profile', to_jsonb(p),
    'has_password', coalesce(a.encrypted_password, '') <> '',
    'has_google', coalesce((a.raw_app_meta_data->'providers') ? 'google'
                    OR a.raw_app_meta_data->>'provider' = 'google', false),
    'order_summary', jsonb_build_object('count', s.cantidad, 'total', s.total)
  )
  INTO v_result
  FROM public.usuarios p
  JOIN auth.users a ON a.id = p.auth_id
  LEFT JOIN LATERAL (
    SELECT count(*) AS cantidad, coalesce(sum(o.total), 0) AS total
    FROM public.pedidos o WHERE o.id_usuario = p.id
  ) s ON true
  WHERE p.auth_id = v_uid;
  IF v_result IS NULL THEN
    RAISE EXCEPTION 'Tu cuenta no tiene un perfil disponible.' USING ERRCODE = 'P0001';
  END IF;
  RETURN v_result;
END;
$$;
REVOKE ALL ON FUNCTION powerbull_private.contexto_usuario() FROM PUBLIC, anon;
GRANT USAGE ON SCHEMA powerbull_private TO authenticated;
GRANT EXECUTE ON FUNCTION powerbull_private.contexto_usuario() TO authenticated;

CREATE OR REPLACE FUNCTION public.powerbull_sesion()
RETURNS jsonb
LANGUAGE sql STABLE SECURITY INVOKER SET search_path = ''
AS $$ SELECT powerbull_private.contexto_usuario(); $$;
REVOKE ALL ON FUNCTION public.powerbull_sesion() FROM PUBLIC, anon;
GRANT EXECUTE ON FUNCTION public.powerbull_sesion() TO authenticated;

