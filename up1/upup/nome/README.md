# Checkout ZapEspião (API MangoFy)

Este diretório contém o checkout personalizado para o produto **ZapEspião**, integrado com a API PIX da MangoFy.

## Configuração Necessária

Para que o checkout funcione corretamente, você precisa editar os seguintes arquivos com suas credenciais reais:

### 1. Backend (PHP)
Edite os arquivos `pagamento.php` e `verifyPayment.php`:
- **$MANGOFY_API_TOKEN**: Substitua pelo seu token de produção da MangoFy.
- **$MANGOFY_STORE_CODE**: Substitua pelo seu código de loja da MangoFy.

### 2. Redirecionamento (Frontend)
No arquivo `index.html`:
- Procure por `window.location.href` (linha ~284).
- Ajuste a URL para onde o usuário deve ser enviado após o pagamento aprovado (ex: página de obrigado, dashboard, área de membros).
  - Atual: `https://zap-espiao.com/painel/`

### 3. Valores e Produto
- **Valor**: O valor está configurado no `index.html` (`amount: 3790` = R$ 37,90) e com fallback no `pagamento.php`.
- **Nome na Fatura**: Configurado como "Licença Vitalícia ZapEspião" no `pagamento.php`.

## Estrutura
- **index.html**: Página de checkout.
- **pagamento.php**: Cria o pedido PIX.
- **verifyPayment.php**: Verifica status do pedido.
- **css/**: Estilos personalizados (Tema Dark Green).
- **js/**: Scripts de suporte (QRCode, UTMs).
