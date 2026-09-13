<?php
/**
 * Perfis de acesso (coluna usuarios_cadastrados.id_tipo_usuario).
 *
 * ATENCAO - divergencia historica, ver SECURITY-FIXES.md:
 * A tabela de apoio `tipo_usuario` do dump traz (1,'Admin'),(2,'Professor'),
 * (3,'Aluno'), mas o codigo sempre tratou 2 como professor/administrador
 * (Login.class.php, install.php e o proprio usuario "admin" do dump usam 2).
 * Ja o tipo 1 era gravado por caminhos antigos de cadastro de ALUNO
 * (area_professor/app/usuario/insert-aluno.php e LabJogo::insertAluno),
 * enquanto Usuario::insertAluno gravava 3.
 *
 * Definicao adotada aqui, alinhada ao comportamento do codigo:
 *   2 = professor/administrador
 *   3 = aluno
 *   1 = aluno legado (aceito para leitura, nunca mais gravado)
 *
 * O tipo 1 NAO da mais acesso a area do professor. A migracao
 * banco/migracoes/2026-09-13-seguranca.sql converte 1 -> 3.
 */
class Perfil
{
    const PROFESSOR = 2;
    const ALUNO = 3;
    /** Valor legado que tambem representa aluno. */
    const ALUNO_LEGADO = 1;

    /** Perfis com acesso a area do professor. */
    public static function professores()
    {
        return array(self::PROFESSOR);
    }

    /** Perfis com acesso a area do aluno e ao laboratorio. */
    public static function todos()
    {
        return array(self::PROFESSOR, self::ALUNO, self::ALUNO_LEGADO);
    }
}
