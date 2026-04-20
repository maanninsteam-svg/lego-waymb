# Documentação Lógica: Integração API MangoFy (Pix)

Esta documentação detalha o fluxo completo e a lógica de funcionamento implementada nos arquivos PHP, explicando como o sistema processa pagamentos Pix de forma transparente ("One Click").

## 1. Fluxo de Criação do Pagamento (`pagamento.php`)

O objetivo deste script é gerar um pedido de pagamento Pix na MangoFy **sem exigir que o usuário preencha um formulário**. Para isso, ele cria um "cliente fantasma" com dados fictícios válidos.

### Passo a Passo da Lógica:

1.  **Entrada de Dados**:
    *   O script recebe um JSON do frontend contendo o `amount` (valor em centavos) e parâmetros UTM de rastreamento.
    *   Se nenhum valor for enviado, ele usa um fallback (configurado no código).

2.  **Geração de Cliente Fictício (Stealth Mode)**:
    *   A API da MangoFy exige dados do cliente (Nome, CPF, Email, Telefone) para emitir um Pix.
    *   O script possui funções (`gerarCPF`, `gerarNome`, etc.) que criam uma identidade válida matematicamente a cada transação.
    *   **Por que isso é feito?** Para permitir um checkout de conversão ultra-rápida onde o usuário não precisa digitar nada, apenas escanear o QR Code.

3.  **Montagem do Payload**:
    *   O script constrói um objeto JSON rigoroso com a estrutura exigida pela MangoFy:
        *   `store_code`: Identificador da loja.
        *   `payment_method`: "pix".
        *   `customer`: Os dados gerados no passo 2.
        *   `items`: Descrição do produto na fatura (ex: "Licença Vitalícia").
        *   `extra`: Metadados com as UTMs para rastreamento de marketing.

4.  **Comunicação com a API**:
    *   Utiliza `cURL` para enviar um `POST` para `https://checkout.mangofy.com.br/api/v1/payment`.
    *   Autenticação via headers: `Authorization` (Token) e `Store-Code`.

5.  **Tratamento da Resposta**:
    *   O script extrai duas informações vitais da resposta da MangoFy:
        *   `payment_code`: O ID único da transação (usado para verificar o status depois).
        *   `pix_qr_code`: O código "Copia e Cola" ou o texto do QR Code.
    *   Esses dados são retornados ao frontend para exibição.

## 2. Fluxo de Verificação (`verifyPayment.php`)

Uma vez que o QR Code é exibido, o frontend precisa saber **quando** o pagamento foi feito para liberar o acesso. O frontend chama este script a cada X segundos (Polling).

### Passo a Passo da Lógica:

1.  **Entrada**:
    *   Recebe o ID da transação (`paymentCode`) que foi gerado no passo anterior.

2.  **Consulta à API**:
    *   Faz um `GET` para `https://checkout.mangofy.com.br/api/v1/payment/{id}`.
    *   Usa as mesmas credenciais de autenticação.

3.  **Análise de Status**:
    *   A API retorna o status atual (ex: `pending`, `approved`, `refunded`).
    *   O script simplifica esse status e retorna para o frontend.
    *   Se o status for `approved` (ou `completed`/`paid`), o frontend executa o redirecionamento.

## 3. Arquitetura de Rastreamento (`js/utm-handler.js`)

O sistema possui uma camada sofisticada de rastreamento para não perder a origem da venda:

*   **Persistência**: Ao chegar na página, o script captura parâmetros de URL (`utm_source`, `fbclid`, etc.).
*   **Armazenamento**: Salva esses dados em Cookies e LocalStorage.
*   **Envio**: Quando o `pagamento.php` é chamado, esses parâmetros são enviados junto no campo `metadata`.
*   **Resultado**: No dashboard da MangoFy, você consegue ver exatamente qual anúncio gerou aquela venda Pix.
