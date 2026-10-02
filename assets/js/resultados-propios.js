(() => {
    const contenedor = document.getElementById('resultados');
    if (!contenedor) {
        return;
    }

    async function init() {
        const sesion = await window.sesionLista;
        if (!sesion) {
            return;
        }
        const data = await apiJson('api/resultados-propios.php');
        if (!data) {
            return;
        }

        renderListaResultados(contenedor, data.resultados, 'Todavia no tenes resultados cargados.');
    }

    init();
})();
