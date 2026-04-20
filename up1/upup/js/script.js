/**
 * Cria uma transação PIX usando a API Mangofy
 */
async function createTransaction() {
  // Dados do cliente
  const name = 'Milena Camila Emanuelly';
  const documentNumber = '88170663385';
  const phone = '86929768706';
  const email = 'andreacarolinanovaes@gmail.com';

  // Obtém o IP do cliente
  let clientIP = '';
  try {
      // Tenta obter o IP público do cliente
      const ipResponse = await fetch('https://api.ipify.org?format=json');
      const ipData = await ipResponse.json();
      clientIP = ipData.ip;
  } catch (error) {
      console.warn('Não foi possível obter IP, usando fallback');
      clientIP = '127.0.0.1'; // Fallback
  }

  // Monta o payload para a API Mangofy
  const paymentData = {
      store_code: MANGOFY_CONFIG.storeCode,
      external_code: `ORDER-${Date.now()}`, // Código único para rastreamento
      payment_method: 'pix',
      payment_format: 'regular',
      installments: 1,
      payment_amount: MANGOFY_CONFIG.payment.amount, // R$ 05,00 em centavos
      shipping_amount: 0,
      postback_url: MANGOFY_CONFIG.postbackUrl || 'https://webhook.site/unique-url',
      
      // Itens da compra
      items: [
          {
              name: 'Taxa de Emissão de Nota Fiscal',
              unit_price: MANGOFY_CONFIG.payment.amount,
              quantity: 1,
              tangible: false
          }
      ],
      
      // Dados do cliente
      customer: {
          email: email,
          name: name,
          document: documentNumber.replace(/[^\d]/g, ''), // Remove formatação
          phone: phone.replace(/[^\d]/g, ''), // Remove formatação
          ip: clientIP // IP do cliente obtido dinamicamente
      },
      
      // Configurações específicas do PIX
      pix: {
          expires_in_days: MANGOFY_CONFIG.payment.expiresInDays
      },
      
      // Informações extras
      extra: {
          userAgent: navigator.userAgent,
          browser: getBrowserName(),
          os: getOSName(),
          device: getDeviceType(),
          url_referer: document.referrer,
          url_full: window.location.href,
          metadata: {
              source: 'tiktok_shop',
              product: 'kit_panelas'
          }
      }
  };

  // Oculta o botão de pagamento
  document.getElementById('paymentButton').style.display = 'none';

  // Exibe a mensagem de carregamento
  document.getElementById('loadingMessage').style.display = 'block';
  document.getElementById('loadingMessage').textContent = 'Gerando código PIX...';

  try {
      // Faz a requisição para criar o pagamento
      const responseData = await mangofyRequest(
          MANGOFY_CONFIG.endpoints.createPayment,
          'POST',
          paymentData
      );

      console.log('Resposta da API Mangofy:', responseData);

      // Verifica se o pagamento foi criado com sucesso
      if (responseData.payment_code) {
          // Armazena o código do pagamento para consultas futuras
          localStorage.setItem('mangofy_payment_code', responseData.payment_code);
          localStorage.setItem('mangofy_payment_status', responseData.payment_status);
          localStorage.setItem('mangofy_external_code', paymentData.external_code);

          // Para PIX, a resposta deve conter os dados do QR Code
          let pixCode = null;
          let pixQrCodeImage = null;

          if (responseData.pix) {
              // A API Mangofy retorna o código PIX em pix_qrcode_text
              pixCode = responseData.pix.pix_qrcode_text || 
                       responseData.pix.qrcode || 
                       responseData.pix.emv || 
                       responseData.pix.code;
              
              pixQrCodeImage = responseData.pix.pix_qrcode_image || 
                              responseData.pix.qrcode_image || 
                              responseData.pix.image;
          }

          console.log('🔍 Código PIX encontrado:', pixCode ? 'SIM' : 'NÃO');
          console.log('🖼️ Imagem QR Code:', pixQrCodeImage ? 'SIM' : 'NÃO');

          if (pixCode) {
              // Preenche o campo com o código PIX
              const pixCodeInput = document.getElementById('pixCode');
              pixCodeInput.value = pixCode;

              // Se houver imagem do QR Code, exibe
              if (pixQrCodeImage && document.getElementById('pixQrCodeImage')) {
                  document.getElementById('pixQrCodeImage').src = pixQrCodeImage;
                  document.getElementById('pixQrCodeImage').style.display = 'block';
              }

              // Oculta a mensagem de carregamento
              document.getElementById('loadingMessage').style.display = 'none';

              // Exibe o container do código PIX
              document.getElementById('pixCodeContainer').style.display = 'block';

              // Inicia a contagem regressiva
              startCountdown();

              // Inicia a verificação periódica do status do pagamento
              startPaymentStatusCheck(responseData.payment_code);
          } else {
              throw new Error('Código PIX não encontrado na resposta da API');
          }
      } else {
          throw new Error('Erro ao criar o pagamento');
      }
  } catch (error) {
      console.error('Erro ao gerar PIX:', error);
      alert('Erro ao gerar o código PIX. Por favor, tente novamente.');
      
      // Oculta a mensagem de carregamento
      document.getElementById('loadingMessage').style.display = 'none';
      
      // Exibe novamente o botão de pagamento
      document.getElementById('paymentButton').style.display = 'block';
  }
}

/**
 * Verifica periodicamente o status do pagamento
 */
function startPaymentStatusCheck(paymentCode) {
  // Verifica a cada 10 segundos
  const checkInterval = setInterval(async () => {
      try {
          const endpoint = MANGOFY_CONFIG.endpoints.getPayment.replace('{paymentCode}', paymentCode);
          const paymentData = await mangofyRequest(endpoint, 'GET');
          
          console.log('Status do pagamento:', paymentData.payment_status);
          
          // Atualiza o status no localStorage
          localStorage.setItem('mangofy_payment_status', paymentData.payment_status);
          
          // Se o pagamento foi aprovado
          if (paymentData.payment_status === 'approved') {
              clearInterval(checkInterval);
              handlePaymentApproved();
          }
          // Se houve erro
          else if (paymentData.payment_status === 'error') {
              clearInterval(checkInterval);
              handlePaymentError();
          }
      } catch (error) {
          console.error('Erro ao verificar status do pagamento:', error);
      }
  }, 10000); // 10 segundos
  
  // Para a verificação após 30 minutos
  setTimeout(() => {
      clearInterval(checkInterval);
  }, 30 * 60 * 1000);
}

/**
 * Manipula pagamento aprovado
 */
function handlePaymentApproved() {
  console.log('✅ Pagamento aprovado! Redirecionando...');
  
  // Dispara evento de conversão (se houver pixel/tracking)
  if (window.pixelId) {
      console.log('Disparando evento de conversão...');
      // Adicione aqui seu código de conversão
  }
  
  // Aguarda 1 segundo e redireciona para página de obrigado
  setTimeout(() => {
      // Mantém os parâmetros UTM na URL
      const currentUrlParams = window.location.search;
      window.location.href = 'obrigado.html' + currentUrlParams;
  }, 1000);
}

/**
 * Manipula erro no pagamento
 */
function handlePaymentError() {
  alert('Houve um erro no processamento do pagamento. Por favor, tente novamente.');
  location.reload();
}

/**
 * Funções auxiliares para detectar informações do navegador
 */
function getBrowserName() {
  const userAgent = navigator.userAgent;
  if (userAgent.includes('Chrome')) return 'Chrome';
  if (userAgent.includes('Firefox')) return 'Firefox';
  if (userAgent.includes('Safari')) return 'Safari';
  if (userAgent.includes('Edge')) return 'Edge';
  if (userAgent.includes('Opera')) return 'Opera';
  return 'Unknown';
}

function getOSName() {
  const userAgent = navigator.userAgent;
  if (userAgent.includes('Windows')) return 'Windows';
  if (userAgent.includes('Mac')) return 'MacOS';
  if (userAgent.includes('Linux')) return 'Linux';
  if (userAgent.includes('Android')) return 'Android';
  if (userAgent.includes('iOS')) return 'iOS';
  return 'Unknown';
}

function getDeviceType() {
  const userAgent = navigator.userAgent;
  if (/(tablet|ipad|playbook|silk)|(android(?!.*mobi))/i.test(userAgent)) {
      return 'Tablet';
  }
  if (/Mobile|Android|iP(hone|od)|IEMobile|BlackBerry|Kindle|Silk-Accelerated|(hpw|web)OS|Opera M(obi|ini)/.test(userAgent)) {
      return 'Mobile';
  }
  return 'Desktop';
}

function copyPixCode() {
  const pixCodeInput = document.getElementById('pixCode');
  const copyButton = document.querySelector('.copy-btn');
  pixCodeInput.select();
  pixCodeInput.setSelectionRange(0, 99999);
  document.execCommand('copy');
  copyButton.textContent = 'CÓDIGO COPIADO';
  setTimeout(() => {
      copyButton.textContent = 'COPIAR CÓDIGO';
  }, 2000);
}

function startCountdown() {
  let timeLeft = 8 * 60 + 19; 
  const timerDisplay = document.getElementById('timer');
  
  const countdownInterval = setInterval(() => {
      const minutes = Math.floor(timeLeft / 60);
      const seconds = timeLeft % 60;
      
      timerDisplay.textContent = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
      
      if (timeLeft <= 0) {
          clearInterval(countdownInterval);
          timerDisplay.textContent = '00:00';
      } else {
          timeLeft--;
      }
  }, 1000);
}

