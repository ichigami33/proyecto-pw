-- Alta automática de perfiles: correo/contraseña y Google.
-- Aplicado al proyecto conectado. No borra usuarios ni cambia sus roles.
CREATE OR REPLACE FUNCTION powerbull_private.crear_perfil()
RETURNS trigger
LANGUAGE plpgsql SECURITY DEFINER SET search_path = ''
AS $$
DECLARE
  v_nombre text;
  v_apellido text;
  v_completo text;
  v_usuario text;
BEGIN
  v_completo := nullif(btrim(coalesce(NEW.raw_user_meta_data->>'full_name', NEW.raw_user_meta_data->>'name', '')), '');
  v_nombre := coalesce(nullif(btrim(NEW.raw_user_meta_data->>'nombre'), ''),
                       nullif(btrim(NEW.raw_user_meta_data->>'given_name'), ''),
                       nullif(split_part(v_completo, ' ', 1), ''), 'Usuario');
  v_apellido := coalesce(nullif(btrim(NEW.raw_user_meta_data->>'apellido'), ''),
                         nullif(btrim(NEW.raw_user_meta_data->>'family_name'), ''),
                         nullif(btrim(substr(v_completo, length(split_part(v_completo, ' ', 1)) + 1)), ''), '');
  v_usuario := nullif(btrim(NEW.raw_user_meta_data->>'nombre_usuario'), '');
  IF v_usuario IS NULL THEN
    v_usuario := left(regexp_replace(split_part(lower(NEW.email), '@', 1), '[^a-z0-9_.-]', '', 'g'), 16)
                 || '_' || left(replace(NEW.id::text, '-', ''), 12);
    IF EXISTS (SELECT 1 FROM public.usuarios WHERE lower("Nombre_usuario") = lower(v_usuario)) THEN
      v_usuario := 'g_' || replace(NEW.id::text, '-', '');
    END IF;
  END IF;
  INSERT INTO public.usuarios
    (auth_id, "Nombre", "Apellido", "Nombre_usuario", "Gmail", "Direccion", rol)
  VALUES
    (NEW.id, left(v_nombre, 80), left(v_apellido, 80), left(v_usuario, 40),
     lower(NEW.email), left(coalesce(NEW.raw_user_meta_data->>'direccion', ''), 200), 'comprador');
  RETURN NEW;
END;
$$;
REVOKE ALL ON FUNCTION powerbull_private.crear_perfil() FROM PUBLIC, anon, authenticated;

