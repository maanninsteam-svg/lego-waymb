# API PIX (Paradise Pags)

Endpoints integrados ao gateway **Paradise Pags** (mesma lógica do payments.php do Paradise).

- **POST api/create-pix.php** – gera cobrança PIX na Paradise Pags.
- **POST api/check-pix.php** – consulta status do PIX na Paradise Pags.

## create-pix

**Request (body JSON):**
- `amount_cents` (number) – valor em centavos
- `customer` (object) – dados do pagador (o que o front enviar é mapeado; fallbacks se faltar):
  - `name` ou `payerName` – nome
  - `email` – e-mail
  - `document` ou `cpf` – CPF (apenas números)
  - `phone` – telefone (apenas números, DDD + número)
- `product_hash` (string, opcional) – hash do produto no painel Paradise; se vazio usa o valor de `api/config.php` (obrigatório ter um produto da **sua** loja).

**Response (200):**
- `qr_code` (string)
- `qr_code_base64` (string, opcional)
- `transaction_id` (string)
- `expires_at` (ISO string)

Para o PIX ser gerado com nome, CPF, email e telefone reais, o formulário do front deve enviar esses campos em `customer`. Se faltar algum, a API usa valores padrão/gerados.

**Parâmetros da URL (XTracky / tracking):**  
- Envie `utm_source` (obrigatório para XTracky) e `preserved_query` (query string completa da URL) no body para não perder nenhum parâmetro no funil.  
- O script nas páginas preserva todos os parâmetros da URL em `sessionStorage` e injeta `utm_source` e `preserved_query` em toda chamada ao create-pix; a API devolve `preserved_query` na resposta para o front manter na URL no redirect (ex.: back-redirect).  
- O `amount` enviado à XTracky é sempre o valor do PIX em centavos (o mesmo gerado na cobrança).

**Produto (productHash):** O gateway Paradise Pags exige um produto cadastrado na **sua** loja. Se aparecer "Produto inválido ou não pertencente a esta loja", crie um produto no painel Paradise Pags (Produtos), copie o hash e defina em `api/config.php` (copie `config.php.example` para `config.php` e preencha `PARADISE_PRODUCT_HASH`).

## check-pix

**Request (body JSON):**
- `transaction_id` (string)

**Response (200):**
- `status`: `"pending"` | `"paid"` | `"approved"` | `"confirmed"`
- `transaction_id` (string)

Quando o gateway retorna pagamento aprovado, a API devolve `status: "paid"` e o front para o polling e segue o fluxo.

## Webhook (postback) – api/webhook-paradise.php

O gateway Paradise Pags envia um **POST** para a sua URL de webhook quando o status da transação muda. Este endpoint recebe esse postback e responde **200 OK**. Em caso de `status: "approved"`, dispara o evento **paid** para a XTracky (mesmo fluxo do check-pix).

**URL de postback para config no painel do gateway (use o domínio do seu site):**

```
https://meusite.com/api/webhook-paradise.php
```

- Troque `meusite.com` pelo domínio real (ex.: `https://seusite.com/api/webhook-paradise.php`).
- Se o cliente trocar de domínio, altere só essa URL no painel — não é preciso alterar código.

**Polling (check-pix):** O front continua usando **check-pix** para consultar o status enquanto o usuário está na tela. Tanto o webhook quanto o check-pix podem disparar o evento paid na XTracky; o primeiro a processar envia e remove o pending, evitando duplicata.

**Payload do gateway (exemplo):**  
Inclui `transaction_id`, `status` (pending, approved, failed, refunded, etc.), `amount`, `customer`, `tracking` (utm_source, utm_campaign, etc.). Só processamos `status === "approved"` para enviar o paid à XTracky.

## Local / deploy

- **PHP (Apache/Hostinger):** Coloque a pasta `ttk-clone` no document root; o front chama `/ttk-clone/api/create-pix.php` e `/ttk-clone/api/check-pix.php` quando acessado em `/ttk-clone/`.
- **Teste local com PHP:** O site **precisa** rodar sob o path `/ttk-clone/` (assets e rotas usam esse prefixo). Por isso o servidor PHP tem de ter como raiz a **pasta que contém** `ttk-clone`, não a pasta `ttk-clone` em si.
  - Na raiz do **app-clonador** (pasta que contém `ttk-clone`), rode:  
    `npm run serve-ttk`  
    ou:  
    `node serve-ttk-php.js`  
  - Acesse: **http://localhost:8080/ttk-clone/**  
  - Confirmar saque (PIX): **http://localhost:8080/ttk-clone/confirmar-saque**  
  - Se você rodar `php -S localhost:8080` **dentro** da pasta `ttk-clone`, a raiz vira `http://localhost:8080/` e o site quebra, porque o front pede `/ttk-clone/assets/...` e não acha.
