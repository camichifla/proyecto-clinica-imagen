const selector = document.getElementById('selector-idioma');

// 1. Función principal para cambiar el idioma
async function cambiarIdioma(idioma) {
    try {
        // Buscamos el archivo JSON externo
        const respuesta = await fetch('idiomas.json');
        const traducciones = await respuesta.json();

        // Actualizamos el atributo 'lang' del HTML para los navegadores y Google
        document.documentElement.lang = idioma;

        // Buscamos todos los elementos con el atributo data-key
        const elementos = document.querySelectorAll('[data-key]');
        
        elementos.forEach(elemento => {
            const clave = elemento.getAttribute('data-key');
            // Cambiamos el texto dinámicamente según el idioma
            elemento.textContent = traducciones[idioma][clave];
        });

        // Marcar que las traducciones ya fueron aplicadas para mostrarlas
        document.body.classList.add('translations-ready');

        // Guardamos la elección en la memoria del navegador
        localStorage.setItem('idiomaPreferido', idioma);
        
        // Hacemos que el menú desplegable muestre el idioma actual
        selector.value = idioma;

    } catch (error) {
        console.error("Error cargando los idiomas, manín:", error);
    }
}

// 2. Escuchar cuando el usuario seleccione otro idioma en el menú
selector.addEventListener('change', (evento) => {
    cambiarIdioma(evento.target.value);
});

// 3. Al cargar la página: usar el idioma guardado, o el español por defecto
const idiomaInicial = localStorage.getItem('idiomaPreferido') || 'es';
cambiarIdioma(idiomaInicial);