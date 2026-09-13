<?php
include('../lab-config.php');
include('conexao.php');

// TODO: Verificar se vale a pena usar input_post por questoes de seguranca
//$comandos = filter_input_array(INPUT_POST, FILTER_DEFAULT);
$comandos = $_REQUEST;
$acao = isset($comandos['acao']) ? $comandos['acao'] : '';

// Login e cadastro carregam credenciais e alteram estado: so por POST.
// Antes aceitavam GET, o que deixava a senha no log do Apache, no historico
// do navegador e no cabecalho Referer.
if (in_array($acao, array('validar_login', 'cadastrar_usuario'), true)
	&& $_SERVER['REQUEST_METHOD'] !== 'POST') {
	Seguranca::abortar(405, 'Metodo nao permitido.');
}

switch ($acao) {
	case "buscar-dados-substancia":
		buscar($comandos['substancia']);
		break;

	case "informacoes_cadastradas":
		buscarInfoVidrarias();
		break;

	case "validar_login":
		Login::logar(
			isset($comandos['usuario']) ? $comandos['usuario'] : '',
			isset($comandos['senha']) ? $comandos['senha'] : ''
		);
		break;

	case "cadastrar_usuario":
		// O perfil NAO vem mais da requisicao. Antes o campo "acesso" era
		// gravado direto em id_tipo_usuario, permitindo que qualquer visitante
		// se cadastrasse como professor/administrador.
		cadastrarUsuario(
			isset($comandos['nome']) ? $comandos['nome'] : '',
			isset($comandos['email']) ? $comandos['email'] : '',
			isset($comandos['usuario']) ? $comandos['usuario'] : '',
			isset($comandos['senha']) ? $comandos['senha'] : ''
		);
		break;

	case "carregar_id_pratica":
		carregarIDPratica2();
		break;

	case "carregar_alunos":
		// Devolve nome e e-mail de todos os alunos: estava publico.
		Login::$permissao_usuario = Perfil::professores();
		if (!Login::logado()) {
			Seguranca::abortar(403, 'Acesso negado.');
		}
		carregarAlunos();
		break;

	case "nome_disciplina":
		nome_disciplina();
		break;

	default:
		Seguranca::abortar(400, 'Acao desconhecida.');
}
function nome_disciplina()
{
	header("Content-type: application/json; charset=utf-8");
	global $banco;

	// Escreve na sessao do usuario: exige estar logado.
	Login::$permissao_usuario = Perfil::todos();
	if (!Login::logado()) {
		Seguranca::abortar(403, 'Acesso negado.');
	}

	try {
		// session_start() ja foi chamado em lab-config.php
		$_SESSION['disciplina'] = @$_REQUEST['nomedisciplina'];
		$_SESSION['id_disciplina'] = @$_REQUEST['id_disciplina'];
		echo json_encode($_SESSION['disciplina']);
	} catch (PDOException $e) {
		echo json_encode(array('sucesso' => false, 'log' => $e->getMessage()));
	}
}

function carregarAlunos()
{
	global $banco;
	try {
		// Aceita o tipo 3 (aluno) e o tipo 1 (aluno legado). Antes so o 1.
		$consulta = $banco->prepare('select id_usuario, nome, email, usuario from usuarios_cadastrados WHERE id_tipo_usuario IN (:aluno, :legado)');
		$consulta->bindValue(':aluno', Perfil::ALUNO, PDO::PARAM_INT);
		$consulta->bindValue(':legado', Perfil::ALUNO_LEGADO, PDO::PARAM_INT);
		$consulta->execute();
		$praticas = $consulta->fetchAll(PDO::FETCH_ASSOC);
		$json = array(
			'data' => array()
		);

		// Percorre as respostas encontradas
		foreach ($praticas as $pratica) {
			array_push(
				$json['data'],
				array(
					$pratica['usuario'],
					$pratica['nome'],
					$pratica['email'],
				)
			);
		}

		// Envia a resposta
		echo json_encode(array('sucesso' => true, 'data' => $json['data']));
	} catch (PDOException $e) {
		echo json_encode(array('sucesso' => false, 'log' => $e->getMessage()));
	}
}

// Busca nomes de práticas disponíveis para serem realizadas
function carregarIDPratica2()
{
	global $banco;
	try {
		$consulta = $banco->prepare('SELECT id as id_pratica, titulo as nome_pratica FROM dados WHERE teste=2');
		$consulta->execute();
		$praticas = $consulta->fetchAll(PDO::FETCH_ASSOC);
		$json = array(
			'data' => array()
		);

		// Percorre as respostas encontradas
		foreach ($praticas as $pratica) {
			array_push(
				$json['data'],
				array(
					'id' => $pratica['id_pratica'],
					'nome' => $pratica['nome_pratica']
				)
			);
		}

		// Envia a resposta
		echo json_encode(array('sucesso' => true, 'data' => $json['data']));
	} catch (PDOException $e) {
		echo json_encode(array('sucesso' => false, 'log' => $e->getMessage()));
	}
}


// Busca nomes de práticas disponíveis para serem realizadas
function carregarIDPratica()
{
	global $banco;
	try {
		$consulta = $banco->prepare('SELECT id_pratica, nome_pratica FROM pratica');
		$consulta->execute();
		$praticas = $consulta->fetchAll(PDO::FETCH_ASSOC);
		$json = array(
			'id' => array(),
			'nome' => array()
		);

		// Percorre as respostas encontradas
		foreach ($praticas as $pratica) {
			array_push($json['id'], $pratica['id_pratica']);
			array_push($json['nome'], $pratica['nome_pratica']);
		}

		// Envia a resposta
		echo json_encode(array('sucesso' => true, 'log' => $json));
	} catch (PDOException $e) {
		echo json_encode(array('sucesso' => false, 'log' => $e->getMessage()));
	}
}

// Busca prática no banco e carrega as informações de configuração
function carregarDadosPratica($id)
{
	global $banco;
	try {
		// Busca a prática pelo id
		$consulta = $banco->prepare('SELECT');
	} catch (PDOException $e) {
		echo json_encode(array('sucesso' => false, 'log' => $e->getMessage()));
	}
}

// Cadastro de novo usuário.
// Cria SEMPRE um aluno: o perfil nunca vem da requisição.
function cadastrarUsuario($nome, $email, $user, $pass)
{
	global $banco;
	header('Content-Type: application/json; charset=utf-8');
	try {
		// Validação dos dados de cadastro (antes só existia no JavaScript)
		$nome = trim((string) $nome);
		$email = trim((string) $email);
		$user = trim((string) $user);
		$pass = (string) $pass;

		if ($nome === '' || mb_strlen($nome) > 45) {
			echo json_encode(array('sucesso' => false, 'log' => 'Informe um nome válido.'));
			return;
		}
		if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 45) {
			echo json_encode(array('sucesso' => false, 'log' => 'Informe um endereço de e-mail válido.'));
			return;
		}
		if (!preg_match('/^[A-Za-z0-9]{3,16}$/', $user)) {
			echo json_encode(array('sucesso' => false, 'log' => 'O nome de usuário deve ter de 3 a 16 letras ou números.'));
			return;
		}
		if (strlen($pass) < 8) {
			echo json_encode(array('sucesso' => false, 'log' => 'A senha deve ter ao menos 8 caracteres.'));
			return;
		}
		if (!userValido($user)) {
			echo json_encode(array('sucesso' => false, 'log' => 'Nome de usuário já existente, falha no cadastro.'));
			return;
		}

		// Cadastra o novo usuário no banco de dados
		$consulta = $banco->prepare('INSERT INTO usuarios_cadastrados (nome, email, usuario, senha, id_tipo_usuario) VALUES(:nome, :email, :usuario, :senha, :id_tipo_usuario)');
		$consulta->execute(array(
			':nome' => $nome,
			':email' => $email,
			':usuario' => $user,
			':senha' => Login::gerarHash($pass),
			':id_tipo_usuario' => Perfil::ALUNO
		));
		echo json_encode(array('sucesso' => true, 'log' => 'Cadastro realizado com sucesso. Fique a vontade para utilizar o sistema.'));
	} catch (PDOException $e) {
		// A coluna `usuario` tem índice UNIQUE: duas requisições simultâneas
		// com o mesmo login caem aqui em vez de criar duplicata.
		error_log('cadastrarUsuario - ' . $e->getMessage());
		echo json_encode(array('sucesso' => false, 'log' => 'Não foi possível concluir o cadastro.'));
	}
}

// Função auxiliar que valida existência de usuário.
// Antes carregava a tabela inteira de usuários e comparava em PHP.
function userValido($user)
{
	global $banco;
	$consulta = $banco->prepare('SELECT 1 FROM usuarios_cadastrados WHERE usuario = :usuario LIMIT 1');
	$consulta->execute(array(':usuario' => $user));
	return $consulta->fetchColumn() === false;
}


// Função de busca no banco de dados
function buscar($substancia)
{
	global $banco;
	try {
		// Busca substância no banco
		$consulta = $banco->prepare('SELECT dados FROM dados_de_substancia WHERE nome = :nome');
		$consulta->execute(array(':nome' => $substancia));
		$resultado = $consulta->fetchAll(PDO::FETCH_ASSOC);
		// Testes
		if (count($resultado) != 1) {
			// Substância não cadastrada
			echo json_encode(array('sucesso' => false, 'log' => 'Substância não encontrada'));
		} else {
			// Envia os dados obtidos do banco
			$resultado = json_decode($resultado[0]['dados'], true);
			echo json_encode(array('sucesso' => true, 'log' => $resultado));
		}
	} catch (PDOException $e) {
		echo json_encode(array('sucesso' => false, 'log' => $e->getMessage()));
	}
}

// Função para busca de vidrarias
function buscarInfoVidrarias()
{
	global $banco;
	try {
		// Busca dados no banco
		$consulta = $banco->prepare('SELECT * FROM objeto');
		$consulta->execute();
		$resultado = $consulta->fetchAll(PDO::FETCH_ASSOC);
		// Prepara o json de resposta
		$dados = array(
			'nomeInterno' => array(),
			'nomeFormatado' => array(),
			'volumes' => array(),
			'substancias' => array()
		);
		foreach ($resultado as $vidraria) {
			array_push($dados['nomeInterno'], $vidraria['nome_interno']);
			array_push($dados['nomeFormatado'], $vidraria['nome_formatado']);
			$volumes = json_decode($vidraria['volumes'], true);
			array_push($dados['volumes'], $volumes);
		}
		// Busca as substâncias cadastradas
		$consulta = $banco->prepare('SELECT nome FROM dados_de_substancia');
		$consulta->execute();
		$resultado = $consulta->fetchAll(PDO::FETCH_ASSOC);
		// Percorre o array de resposta
		foreach ($resultado as $substancia) {
			array_push($dados['substancias'], $substancia['nome']);
		}
		// Envia a resposta
		echo json_encode(array('sucesso' => true, 'log' => $dados));
	} catch (PDOException $e) {
		echo json_encode(array('sucesso' => false, 'log' => $e->getMessage()));
	}
}
