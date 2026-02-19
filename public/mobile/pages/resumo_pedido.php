<!-- Resumo do Pedido - Diárias Village -->
<style>
  .material-symbols-outlined {
    font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
  }
</style>

<div class="bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 min-h-screen flex flex-col">
  <!-- Top App Bar -->
  <header class="sticky top-0 z-20 bg-white/80 dark:bg-background-dark/80 backdrop-blur-md border-b border-primary/10">
    <div class="flex items-center p-4 justify-between max-w-lg mx-auto w-full">
      <button data-go="grade_oficinas" class="text-primary p-2 hover:bg-primary/10 rounded-full transition-colors">
        <span class="material-symbols-outlined text-2xl">arrow_back</span>
      </button>
      <h1 class="text-primary text-lg font-bold leading-tight tracking-tight flex-1 text-center pr-10">Resumo do Pedido</h1>
    </div>
  </header>

  <main class="flex-1 w-full max-w-lg mx-auto pb-32">
    <!-- Workshops -->
    <section class="px-4 pt-6">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-primary text-xl font-bold">Seus Workshops</h2>
        <span class="text-xs font-medium px-2 py-1 bg-primary/10 text-primary rounded-full">2 ITENS</span>
      </div>
      <div class="space-y-3">
        <div class="flex items-center gap-4 bg-white dark:bg-slate-800 p-4 rounded-xl shadow-sm border border-slate-100 dark:border-slate-700">
          <div class="bg-primary/5 dark:bg-primary/20 text-primary p-3 rounded-lg">
            <span class="material-symbols-outlined">event_available</span>
          </div>
          <div class="flex-1 min-w-0">
            <p class="font-semibold text-base truncate">Workshop de Fotografia Pro</p>
            <p class="text-slate-500 dark:text-slate-400 text-sm">Sábado, 14 de Outubro</p>
          </div>
          <div class="text-right">
            <p class="font-bold text-primary">R$ 150,00</p>
          </div>
        </div>
        <div class="flex items-center gap-4 bg-white dark:bg-slate-800 p-4 rounded-xl shadow-sm border border-slate-100 dark:border-slate-700">
          <div class="bg-primary/5 dark:bg-primary/20 text-primary p-3 rounded-lg">
            <span class="material-symbols-outlined">video_library</span>
          </div>
          <div class="flex-1 min-w-0">
            <p class="font-semibold text-base truncate">Edição de Vídeo Mobile</p>
            <p class="text-slate-500 dark:text-slate-400 text-sm">Domingo, 15 de Outubro</p>
          </div>
          <div class="text-right">
            <p class="font-bold text-primary">R$ 200,00</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Dados do Pagador -->
    <section class="px-4 mt-8">
      <h2 class="text-primary text-xl font-bold mb-4">Dados do Pagador</h2>
      <div class="bg-white dark:bg-slate-800 p-5 rounded-xl shadow-sm border border-slate-100 dark:border-slate-700">
        <div class="flex items-start justify-between">
          <div class="space-y-3">
            <div>
              <p class="text-xs uppercase tracking-wider text-slate-500 font-semibold mb-1">Responsável</p>
              <p class="font-medium text-slate-900 dark:text-slate-100">Guilherme Silva Santos</p>
            </div>
            <div>
              <p class="text-xs uppercase tracking-wider text-slate-500 font-semibold mb-1">CPF cadastrado</p>
              <div class="flex items-center gap-2">
                <p class="font-medium text-slate-900 dark:text-slate-100 tracking-wider">42*.***.***-09</p>
                <span class="material-symbols-outlined text-sm text-primary">verified</span>
              </div>
            </div>
          </div>
          <button class="text-primary text-sm font-semibold hover:underline">Editar</button>
        </div>
      </div>
    </section>

    <!-- Forma de Pagamento -->
    <section class="px-4 mt-8">
      <h2 class="text-primary text-xl font-bold mb-4">Forma de Pagamento</h2>
      <div class="bg-white dark:bg-slate-800 p-4 rounded-xl shadow-sm border-2 border-primary/20 flex items-center justify-between">
        <div class="flex items-center gap-4">
          <div class="bg-[#32BCAD]/10 text-[#32BCAD] p-2 rounded-lg">
            <span class="material-symbols-outlined font-bold">account_balance_wallet</span>
          </div>
          <div>
            <p class="font-bold text-slate-900 dark:text-slate-100">PIX</p>
            <p class="text-slate-500 dark:text-slate-400 text-xs">Aprovação instantânea</p>
          </div>
        </div>
        <div class="flex items-center gap-2 text-primary">
          <span class="text-sm font-semibold">Alterar</span>
          <span class="material-symbols-outlined text-sm">chevron_right</span>
        </div>
      </div>
    </section>

    <!-- Resumo -->
    <section class="px-4 mt-8 pb-8">
      <div class="space-y-2 border-t border-slate-200 dark:border-slate-700 pt-4">
        <div class="flex justify-between text-slate-500">
          <span>Subtotal</span>
          <span>R$ 350,00</span>
        </div>
        <div class="flex justify-between text-slate-500">
          <span>Taxas de serviço</span>
          <span class="text-green-600 font-medium">Grátis</span>
        </div>
        <div class="flex justify-between items-end pt-2">
          <span class="text-slate-900 dark:text-slate-100 font-bold text-lg">Total do pedido</span>
          <span class="text-primary font-bold text-2xl">R$ 350,00</span>
        </div>
      </div>
    </section>
  </main>

  <!-- Sticky Bottom Bar -->
  <div class="fixed bottom-0 left-0 right-0 bg-white dark:bg-slate-900 border-t border-slate-100 dark:border-slate-800 p-4 pb-8 z-30 shadow-[0_-4px_20px_rgba(0,0,0,0.05)]">
    <div class="max-w-lg mx-auto">
      <button class="w-full bg-primary hover:bg-primary/90 text-white font-bold py-4 rounded-xl transition-all active:scale-[0.98] flex items-center justify-center gap-2 shadow-lg shadow-primary/20">
        <span>Continuar para pagamento</span>
        <span class="material-symbols-outlined">arrow_forward</span>
      </button>
      <p class="text-center text-[10px] text-slate-400 mt-3 flex items-center justify-center gap-1">
        <span class="material-symbols-outlined text-[12px]">lock</span>
        Pagamento processado em ambiente seguro
      </p>
    </div>
  </div>
</div>
