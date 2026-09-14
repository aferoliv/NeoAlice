/**
 * Injeta o token CSRF em toda chamada jQuery que altere estado.
 *
 * Usa cabecalho em vez de mexer no corpo da requisicao porque assim funciona
 * igual para os tres formatos que o projeto usa: objeto, array de
 * {name, value} e FormData (upload de material didatico).
 *
 * Os formularios HTML comuns nao passam por aqui: eles levam o campo oculto
 * _csrf, gerado por Seguranca::campoCsrf().
 */
$(document).ajaxSend(function (event, jqxhr, settings) {
    if (typeof CSRF_TOKEN === 'undefined' || !CSRF_TOKEN) {
        return;
    }
    if (settings.crossDomain) {
        return;
    }
    var metodo = (settings.type || settings.method || 'GET').toUpperCase();
    if (metodo === 'GET' || metodo === 'HEAD' || metodo === 'OPTIONS') {
        return;
    }
    jqxhr.setRequestHeader('X-CSRF-Token', CSRF_TOKEN);
});
