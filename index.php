<?php

class PagCompletoAPI {
    private $base_url;
    private $endpoint;
    private $api_key;

    public function __construct($api_key) {
        // Configurações da API do PagCompleto
        $this->base_url = "https://api11.ecompleto.com.br";
        $this->endpoint = "/exams/processTransaction";
        $this->api_key = $api_key;
    }

    public function processarPagamento($id_pedido, $valor) {
        // Monta os dados para a requisição
        $url = $this->base_url . $this->endpoint;
        $headers = ["Authorization: " . $this->api_key];
        $payload = [
            "external_order_id" => $id_pedido,
            "amount" => $valor,
            "card_number" => "4111111111111111",
            "card_cvv" => "123",
            "card_expiration_date" => "0922",
            "card_holder_name" => "Morpheus Fishburne",
            "customer" => [
                "external_id" => "3311",
                "name" => "Morpheus Fishburne",
                "type" => "individual",
                "email" => "mopheus@nabucodonozor.com",
                "documents" => [["type" => "cpf", "number" => "30621143049"]],
                "birthday" => "1965-01-01"
            ]
        ];

        // Configurações da requisição cURL
        $options = [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false
        ];

        // Executa a requisição cURL
        $curl = curl_init($url);
        curl_setopt_array($curl, $options);
        $response = curl_exec($curl);
        $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        // Verifica o código de status da resposta
        if ($statusCode == 200) {
            return json_decode($response, true);
        } else {
            return null;
        }
    }
}

function atualizarPedido($conexao, $id_pedido, $nova_situacao, $retorno_api) {
    try {
        // Atualiza o pedido com a nova situação
        $query = $conexao->prepare("UPDATE pedidos SET id_situacao = ? WHERE id = ?");
        $query->execute([$nova_situacao, $id_pedido]);

        // Atualiza os dados do pagamento com o retorno da API
        $query = $conexao->prepare("UPDATE pedidos_pagamentos SET retorno_intermediador = ? WHERE id_pedido = ?");
        $query->execute([json_encode($retorno_api), $id_pedido]);
    } catch (PDOException $e) {
        // Em caso de erro, exibe uma mensagem de erro
        echo "Erro ao atualizar pedido no banco de dados: " . $e->getMessage();
    }
}

function processarPedidos($conexao, $pag_completo) {
    try {
        // Busca os pedidos que atendem aos critérios definidos
        $query = $conexao->prepare("
            SELECT pedidos.id, pedidos.valor_total
            FROM pedidos
            JOIN formas_pagamento ON pedidos.id_formapagto = formas_pagamento.id
            JOIN pedido_situacao ON pedidos.id_situacao = pedido_situacao.id
            WHERE formas_pagamento.id = 3
            AND pedido_situacao.id = 1
        ");
        $query->execute();
        $pedidos = $query->fetchAll(PDO::FETCH_ASSOC);

        // Processa cada pedido encontrado
        foreach ($pedidos as $pedido) {
            $id_pedido = $pedido['id'];
            $valor = $pedido['valor_total'];
            $retorno_api = $pag_completo->processarPagamento($id_pedido, $valor);
            $nova_situacao = $retorno_api && !$retorno_api['Error'] ? 2 : 3;
            atualizarPedido($conexao, $id_pedido, $nova_situacao, $retorno_api);
        }
    } catch (PDOException $e) {
        // Em caso de erro, exibe uma mensagem de erro
        echo "Erro ao buscar pedidos no banco de dados: " . $e->getMessage();
    }
}

try {
    // Conexão com o banco de dados
    $conexao = new PDO('mysql:host=localhost;dbname=testeEcompleto', 'root', '');
    $conexao->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Chave da API do PagCompleto
    $api_key = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdG9yZUlkIjoiNCIsInVzZXJJZCI6IjkwNDQiLCJpYXQiOjE3MDc5Mzg0ODUsImV4cCI6MTcwNzk0MjA4NX0.KYZGGBfSg3NyR29diuTYoDVs6Es20Zkf-qrhzwvdYvo"; // Substitua pelo valor real da sua API_KEY

    // Instância da classe da API do PagCompleto
    $pag_completo = new PagCompletoAPI($api_key);

    // Processamento dos pedidos
    processarPedidos($conexao, $pag_completo);

} catch (PDOException $e) {
    // Em caso de erro na conexão com o banco de dados, exibe uma mensagem de erro
    echo "Erro ao conectar ao banco de dados: " . $e->getMessage();
} finally {
    // Fecha a conexão com o banco de dados
    $conexao = null;
}
