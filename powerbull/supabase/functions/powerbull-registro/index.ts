// Alta de cuentas de Powerbull. La clave de servicio existe solo en Supabase.
const json = (data: unknown, status = 200) =>
  new Response(JSON.stringify(data), {
    status,
    headers: {
      "Content-Type": "application/json; charset=utf-8",
      "Cache-Control": "no-store",
    },
  });
Deno.serve(async (req: Request) => {
  if (req.method !== "POST")
    return json({ error: "Método no permitido." }, 405);
  try {
    if (Number(req.headers.get("content-length") || 0) > 10000)
      return json({ error: "Solicitud demasiado grande." }, 413);
    const input = await req.json();
    const text = (name: string) =>
      typeof input[name] === "string" ? input[name].trim() : "";
    const email = text("email").toLowerCase();
    const password = typeof input.password === "string" ? input.password : "";
    const nombre = text("nombre"),
      apellido = text("apellido"),
      usuario = text("nombre_usuario"),
      direccion = text("direccion");
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email) || email.length > 254)
      return json({ error: "Ingresá un correo válido." }, 400);
    if (password.length < 6 || new TextEncoder().encode(password).length > 72)
      return json(
        {
          error:
            "La contraseña debe tener al menos 6 caracteres y un máximo de 72 bytes.",
        },
        400,
      );
    if (
      nombre.length < 2 ||
      nombre.length > 80 ||
      apellido.length < 2 ||
      apellido.length > 80
    )
      return json({ error: "Completá tu nombre y apellido." }, 400);
    if (!/^[a-zA-Z0-9_.-]{3,30}$/.test(usuario))
      return json(
        {
          error:
            "El nombre de usuario debe tener entre 3 y 30 letras, números, puntos o guiones.",
        },
        400,
      );
    if (direccion.length < 5 || direccion.length > 200)
      return json(
        { error: "Completá una dirección de entre 5 y 200 caracteres." },
        400,
      );
    const url = Deno.env.get("SUPABASE_URL")!;
    const secret = Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!;
    const headers = {
      apikey: secret,
      Authorization: `Bearer ${secret}`,
      "Content-Type": "application/json",
    };
    const ip = (req.headers.get("x-forwarded-for") || "unknown")
      .split(",")[0]
      .trim();
    const bytes = await crypto.subtle.digest(
      "SHA-256",
      new TextEncoder().encode(ip),
    );
    const fingerprint = Array.from(new Uint8Array(bytes))
      .map((b) => b.toString(16).padStart(2, "0"))
      .join("");
    const rate = await fetch(`${url}/rest/v1/rpc/powerbull_limite_registro`, {
      method: "POST",
      headers,
      body: JSON.stringify({ p_huella: fingerprint }),
    });
    if (!rate.ok)
      return json(
        { error: "No pudimos crear la cuenta. Intentá nuevamente." },
        503,
      );
    if (!(await rate.json()))
      return json(
        { error: "Se hicieron demasiados registros. Intentá más tarde." },
        429,
      );
    const check = await fetch(
      `${url}/rest/v1/usuarios?select=id&Nombre_usuario=ilike.${encodeURIComponent(usuario.replaceAll("_", "\\_"))}`,
      { headers },
    );
    if (check.ok && (await check.json()).length)
      return json({ error: "Ese nombre de usuario ya está en uso." }, 409);
    const response = await fetch(`${url}/auth/v1/admin/users`, {
      method: "POST",
      headers,
      body: JSON.stringify({
        email,
        password,
        email_confirm: true,
        user_metadata: { nombre, apellido, nombre_usuario: usuario, direccion },
      }),
    });
    const result = await response.json();
    if (!response.ok) {
      const message = String(
        result.msg || result.message || result.error_description || "",
      );
      if (/already|registered|exists/i.test(message))
        return json({ error: "Ya existe una cuenta con ese correo." }, 409);
      if (/password/i.test(message))
        return json(
          {
            error:
              "La contraseña no cumple los requisitos. Usá al menos 6 caracteres.",
          },
          400,
        );
      return json(
        {
          error:
            "No pudimos crear la cuenta. Revisá los datos e intentá de nuevo.",
        },
        400,
      );
    }
    return json({ ok: true }, 201);
  } catch {
    return json(
      {
        error:
          "No pudimos procesar el registro. Revisá los datos e intentá de nuevo.",
      },
      400,
    );
  }
});
