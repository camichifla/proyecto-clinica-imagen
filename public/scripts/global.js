// Helper: pedir JSON y lanzar error si status != ok
async function jsonFetch(url, options = {}) {
    const res = await fetch(url, options);
    const data = await res.json();
    if (!res.ok) throw new Error(data.message || 'Error en la petición');
    return data;
}

// Pequeñas utilidades globales pueden agregarse aquí.
