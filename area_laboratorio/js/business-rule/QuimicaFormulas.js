class QuimicaFormulas {
    /**
     * QuimicaFormulas.calcularVariacao(2, 1);
     * variacao que o numero pode ir para baixo ou para cima
     * neste exemplo podemos obter do numero 1 ao numero 3
     * @author wellerson
     */
    static calcularVariacao(valor, variacao) {
        // Correcao 2026-09: antes era (Math.random() * variacao) - variacao,
        // que resulta sempre em [-variacao, 0). O erro so ia para baixo, ao
        // contrario do que o comentario e o exemplo acima descrevem, e toda
        // leitura saia menor que o valor real (pH no Phmetro, absorbancia no
        // Espectrofotometro). Faltava o fator 2 para o intervalo ficar
        // centrado em zero: [-variacao, +variacao).
        var vf = (Math.random() * 2 * variacao) - variacao;
        return (valor + vf).toFixed(3);
    }

    /** função de montecarlo
     * @verificada dia 31/10/2019
     * @param {number} sd é o desvio padrão
     * @returns {number} numero aleatorio
     * QuimicaFormulas.MC(1.5)
     */
    static MC(sd) {
        var mc;
        var i = 0;
        while (i == 0) {
            var random1 = 2 * Math.random() - 1;
            var random2 = 2 * Math.random() - 1;
            var Sum = random2 * random2 + random1 * random1;
            if (Sum <= 1) {
                var M2 = -2 * (Math.log(Sum) / Sum);
                var M = Math.sqrt(M2);
                mc = random1 * M * sd;
                break;
            }
        }
        return mc;
    }

    /**
     * verifica se está vazio
     * @param {*} arg  valor
     * @returns {boolean}
     */
    static isMissing(arg) {
        if (!arg || arg == "" || arg == undefined || arg.length <= 0) {
            return true;
        } else {
            return false;
        }
    }
}