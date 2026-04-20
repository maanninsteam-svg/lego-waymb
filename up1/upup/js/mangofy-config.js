/**
 * Configuração da API Mangofy
 * Documentação: https://checkout.mangofy.com.br/api/v1
 */

const MANGOFY_CONFIG = {
    // Credenciais da API 
    apiKey: '22a13f6f48064863af2f412e8c1877b2blegxppxapplkmktuswuo58iyb5gu6m',
    storeCode: 'vstnk0xd8a0150641dda2100ed7c349c3ec475820741d8f012d46a92c51df51757344b95',
    
    // URLs da API
    baseUrl: 'https://checkout.mangofy.com.br/api/v1',
    
    // Endpoints
    endpoints: {
        createPayment: '/payment',
        getPayment: '/payment/{paymentCode}'
    },
    
    // Configurações de pagamento
    payment: {
        method: 'pix',
        format: 'regular',
        amount: 0500, // R$ 05,00 em centavos
        installments: 1,
        expiresInDays: 1
    },
    
    // URL para receber webhooks 
    // IMPORTANTE: Configure uma URL real para receber notificações de pagamento
    // Pode usar webhook.site para testes: https://webhook.site
    postbackUrl: 'https://webhook.site/unique-url' // ⚠️ Configure sua URL de webhook aqui
};

// Função auxiliar para fazer requisições à API Mangofy
async function mangofyRequest(endpoint, method = 'GET', data = null) {
    // Valida se as credenciais estão configuradas
    if (!MANGOFY_CONFIG.apiKey) {
        const errorMsg = '⚠️ ERRO DE CONFIGURAÇÃO: API Key não está configurada!';
        console.error(errorMsg);
        alert(errorMsg);
        throw new Error('API Key não configurada');
    }
    
    if (!MANGOFY_CONFIG.storeCode) {
        const errorMsg = '⚠️ ERRO DE CONFIGURAÇÃO: Store Code não está configurado!';
        console.error(errorMsg);
        alert(errorMsg);
        throw new Error('Store Code não configurado');
    }
    
    const url = `${MANGOFY_CONFIG.baseUrl}${endpoint}`;
    
    const headers = {
        'Authorization': MANGOFY_CONFIG.apiKey,
        'Store-Code': MANGOFY_CONFIG.storeCode,
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    };
    
    const options = {
        method: method,
        headers: headers
    };
    
    if (data && method !== 'GET') {
        options.body = JSON.stringify(data);
    }
    
    console.log('📤 Requisição Mangofy:', {
        url: url,
        method: method,
        storeCode: MANGOFY_CONFIG.storeCode,
        hasApiKey: !!MANGOFY_CONFIG.apiKey
    });
    
    try {
        const response = await fetch(url, options);
        
        console.log('📥 Resposta Mangofy:', {
            status: response.status,
            statusText: response.statusText,
            ok: response.ok
        });
        
        const responseData = await response.json();
        
        if (!response.ok) {
            // Mensagens de erro específicas
            if (response.status === 401) {
                throw new Error('❌ Erro 401: Credenciais inválidas. Verifique sua API Key e Store Code.');
            } else if (response.status === 404) {
                throw new Error('❌ Erro 404: ' + (responseData.message || 'Recurso não encontrado'));
            } else if (response.status === 400) {
                throw new Error('❌ Erro 400: ' + (responseData.message || 'Requisição inválida'));
            } else {
                throw new Error(responseData.message || `Erro ${response.status} na API Mangofy`);
            }
        }
        
        return responseData;
    } catch (error) {
        console.error('❌ Erro na API Mangofy:', error);
        throw error;
    }
}

