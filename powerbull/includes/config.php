<?php
// Claves públicas de este proyecto. Las claves de servicio no se usan en PHP.
return [
    // Vacío: detecta localhost. Al publicar, indicar la URL HTTPS completa de Powerbull.
    'app_url' => getenv('POWERBULL_APP_URL') ?: '',
    // Opcional para instalaciones de PHP con un archivo de certificados propio.
    'ca_bundle' => getenv('POWERBULL_CA_BUNDLE') ?: '',
    'supabase_url' => getenv('POWERBULL_SUPABASE_URL') ?: 'https://mbzenzxffillbpmybuak.supabase.co',
    'supabase_key' => getenv('POWERBULL_SUPABASE_KEY') ?: 'sb_publishable_SEWitQZMgnKWkaiNwbbz7A__39WrduO',
    'anon_jwt' => getenv('POWERBULL_ANON_JWT') ?: 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Im1iemVuenhmZmlsbGJwbXlidWFrIiwicm9sZSI6ImFub24iLCJpYXQiOjE3OTIxNjI4OTksImV4cCI6MjEwNzczODg5OX0.TT8aRveiMVhT7uVUsP2i6GYkwyO9nrcRCmA6viYIppk',
];
