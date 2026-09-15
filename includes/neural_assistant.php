<?php
/**
 * LUB-TEK - UI do Assistente Neural (Lúbria)
 * Design Dark Mode premium com suporte contextual.
 */
?>
<!-- Botão Flutuante -->
<button id="neural-assistant-trigger" class="floating-btn" onclick="toggleNeuralChat()" style="position: fixed; bottom: 30px; right: 30px; width: 60px; height: 60px; border-radius: 50%; background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%); color: white; border: none; box-shadow: 0 10px 25px rgba(139, 92, 246, 0.4); cursor: pointer; z-index: 99999; display: flex; align-items: center; justify-content: center; transition: transform 0.3s;">
    <i data-lucide="bot" style="width: 30px; height: 30px;"></i>
    <div style="position: absolute; top: -2px; right: -2px; width: 20px; height: 20px; background: #10b981; border-radius: 50%; border: 2px solid white; display: flex; align-items: center; justify-content: center; font-size: 8px; font-weight: 800; animation: neuralPulse 2s infinite;">AI</div>
</button>

<!-- Janela do Chat -->
<div id="neural-chat-window" style="position: fixed; bottom: 100px; right: 30px; width: 380px; max-width: calc(100vw - 30px); height: 600px; max-height: calc(100vh - 130px); background: rgba(15, 23, 42, 0.95); backdrop-filter: blur(15px); -webkit-backdrop-filter: blur(15px); border: 1px solid rgba(139, 92, 246, 0.3); border-radius: 16px; box-shadow: 0 20px 40px rgba(0,0,0,0.5); z-index: 99999; display: none; flex-direction: column; overflow: hidden; transition: opacity 0.3s;">
    
    <!-- Cabeçalho -->
    <div style="padding: 15px 20px; background: linear-gradient(90deg, rgba(139, 92, 246, 0.2) 0%, transparent 100%); border-bottom: 1px solid rgba(255,255,255,0.05); display: flex; justify-content: space-between; align-items: center; flex-shrink: 0;">
        <div style="display: flex; align-items: center; gap: 10px;">
            <div style="width: 10px; height: 10px; border-radius: 50%; background: #10b981; box-shadow: 0 0 8px #10b981;"></div>
            <div>
                <h3 style="margin: 0; font-size: 1.1rem; color: #fff; font-weight: 700; line-height: 1.2;">Lúbria</h3>
                <div id="neural-context-label" style="font-size: 0.7rem; color: #a78bfa; font-weight: 600;">Seu guia do sistema</div>
            </div>
        </div>
        <button onclick="toggleNeuralChat()" style="background: none; border: none; color: #94a3b8; cursor: pointer; display: flex; align-items: center; justify-content: center;">
            <i data-lucide="x" style="width: 18px; height: 18px;"></i>
        </button>
    </div>

    <!-- Área de Mensagens -->
    <div id="neural-messages" style="flex: 1; padding: 20px; overflow-y: auto; display: flex; flex-direction: column; gap: 15px; background: rgba(10, 15, 30, 0.4); scrollbar-width: thin; scrollbar-color: rgba(139, 92, 246, 0.3) transparent;">
        <!-- Mensagens serão injetadas dinamicamente via JS -->
    </div>

    <!-- Rodapé de Input -->
    <div style="padding: 15px; background: rgba(15, 23, 42, 0.98); border-top: 1px solid rgba(255,255,255,0.05); flex-shrink: 0; display: flex; flex-direction: column; gap: 10px;">
        <!-- Chips Rápidos -->
        <div id="neural-quick-chips" style="display: flex; flex-wrap: wrap; gap: 6px; max-height: 80px; overflow-y: auto; scrollbar-width: none;"></div>

        <label style="display:flex;align-items:center;gap:8px;font-size:0.75rem;color:#94a3b8;cursor:pointer;user-select:none;">
            <input type="checkbox" id="neural-force-ai" style="accent-color:#8b5cf6;">
            IA avançada (Gemini) — sob demanda, consome quota
        </label>
        
        <div style="display: flex; gap: 10px;">
            <input type="text" id="neural-input" class="lubria-chat-input chat-input-field" placeholder="Pergunte sobre um ativo ou falha..." style="flex: 1; padding: 12px; border-radius: 8px; border: 1px solid #38bdf8 !important; background: #1e293b !important; color: #ffffff !important; caret-color: #38bdf8 !important; font-size: 0.9rem;" autocomplete="off" onkeydown="if(event.key==='Enter'){event.preventDefault();sendNeuralMessage();}">
            <button type="button" onclick="sendNeuralMessage()" id="neural-send-btn" style="background: #8b5cf6; color: white; border: none; border-radius: 8px; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: background 0.2s;">
                <i data-lucide="send" style="width: 18px;"></i>
            </button>
        </div>
    </div>
</div>

<style>
@keyframes neuralPulse {
    0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
    70% { transform: scale(1); box-shadow: 0 0 0 8px rgba(16, 185, 129, 0); }
    100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
}

#neural-assistant-trigger:hover {
    transform: scale(1.08) !important;
}

#neural-chat-window.neural-open {
    display: flex !important;
    animation: neuralSlideUp 0.25s ease;
}

@keyframes neuralSlideUp {
    from { transform: translateY(16px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

/* Bolhas de mensagens formatadas em Dark Mode */
.neural-msg-user {
    align-self: flex-end;
    background: #8b5cf6;
    padding: 10px 14px;
    border-radius: 12px;
    border-top-right-radius: 2px;
    max-width: 85%;
    color: white;
    font-size: 0.9rem;
    box-shadow: 0 4px 12px rgba(139, 92, 246, 0.25);
    line-height: 1.4;
}

.neural-msg-ai {
    align-self: flex-start;
    background: rgba(139, 92, 246, 0.15);
    padding: 12px 16px;
    border-radius: 12px;
    border-top-left-radius: 2px;
    max-width: 85%;
    color: #e2e8f0;
    font-size: 0.9rem;
    line-height: 1.5;
    border: 1px solid rgba(139, 92, 246, 0.25);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.neural-chip {
    background: rgba(139, 92, 246, 0.2);
    color: #c084fc;
    border: 1px solid rgba(139, 92, 246, 0.3);
    padding: 5px 11px;
    border-radius: 16px;
    font-size: 0.75rem;
    cursor: pointer;
    font-weight: 600;
    transition: background 0.2s, color 0.2s;
    text-align: left;
}

.neural-chip:hover {
    background: rgba(139, 92, 246, 0.35);
    color: white;
}

.neural-typing-dots::after {
    content: '...';
    animation: neuralDots 1.2s infinite;
}

@keyframes neuralDots {
    0%, 20% { content: '.'; }
    40% { content: '..'; }
    60%, 100% { content: '...'; }
}

@media (max-width: 480px) {
    #neural-chat-window {
        right: 10px;
        left: 10px;
        width: auto;
        bottom: 90px;
    }
    #neural-assistant-trigger {
        bottom: 20px;
        right: 20px;
    }
}
</style>
