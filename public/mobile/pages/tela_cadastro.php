<!-- Cadastro - Diárias Village -->
<style>
  .material-symbols-outlined {
    font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
  }
</style>

<div class="min-h-screen flex flex-col items-center bg-background-light dark:bg-background-dark">
  <!-- Top Navigation -->
  <header class="w-full max-w-[480px] flex items-center bg-white dark:bg-slate-900/50 p-4 sticky top-0 z-10 border-b border-slate-200 dark:border-slate-800">
    <button data-go="tela_inicial" aria-label="Voltar" class="text-primary dark:text-slate-100 flex size-12 shrink-0 items-center justify-center rounded-full hover:bg-primary/10 transition-colors">
      <span class="material-symbols-outlined text-[28px]">arrow_back</span>
    </button>
    <h1 class="text-slate-900 dark:text-white text-lg font-bold leading-tight tracking-tight flex-1 text-center pr-12">Crie sua conta</h1>
  </header>

  <main class="w-full max-w-[480px] bg-white dark:bg-slate-900 flex-1 flex flex-col px-6 py-8 shadow-sm">
    <div class="mb-8">
      <div class="flex items-center gap-3 mb-4">
        <div class="bg-primary/10 p-2 rounded-lg">
          <span class="material-symbols-outlined text-primary text-3xl">domain</span>
        </div>
        <h2 class="text-primary dark:text-white text-2xl font-bold">Diárias Village</h2>
      </div>
      <h3 class="text-slate-900 dark:text-slate-100 text-[28px] font-bold leading-tight mb-2">Bem-vindo</h3>
      <p class="text-slate-600 dark:text-slate-400 text-base font-normal">Preencha os dados abaixo para começar sua jornada conosco.</p>
    </div>

    <form class="flex flex-col gap-5" onsubmit="return false;">
      <!-- CPF -->
      <div class="flex flex-col gap-2">
        <label class="text-slate-900 dark:text-slate-200 text-sm font-semibold flex justify-between">
          <span>CPF</span>
          <span class="text-xs font-normal text-slate-500">Obrigatório</span>
        </label>
        <div class="relative group">
          <input class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-4 text-slate-900 dark:text-white focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all placeholder:text-slate-400" placeholder="000.000.000-00" type="text"/>
          <span class="material-symbols-outlined absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 text-xl">badge</span>
        </div>
      </div>
      <!-- Email -->
      <div class="flex flex-col gap-2">
        <label class="text-slate-900 dark:text-slate-200 text-sm font-semibold">E-mail</label>
        <div class="relative">
          <input class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-4 text-slate-900 dark:text-white focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all placeholder:text-slate-400" placeholder="exemplo@email.com" type="email"/>
          <span class="material-symbols-outlined absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 text-xl">mail</span>
        </div>
      </div>
      <!-- Senha -->
      <div class="flex flex-col gap-2">
        <label class="text-slate-900 dark:text-slate-200 text-sm font-semibold">Senha</label>
        <div class="relative">
          <input class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-4 text-slate-900 dark:text-white focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all placeholder:text-slate-400" placeholder="••••••••" type="password"/>
          <button class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-primary transition-colors" type="button">
            <span class="material-symbols-outlined text-xl">visibility</span>
          </button>
        </div>
        <p class="text-xs text-slate-500 mt-1">Mínimo 8 caracteres, incluindo letras e números.</p>
      </div>
      <!-- Confirmar Senha -->
      <div class="flex flex-col gap-2">
        <label class="text-slate-900 dark:text-slate-200 text-sm font-semibold">Confirmar Senha</label>
        <div class="relative">
          <input class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-4 text-slate-900 dark:text-white focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all placeholder:text-slate-400" placeholder="••••••••" type="password"/>
          <span class="material-symbols-outlined absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 text-xl">lock_reset</span>
        </div>
      </div>
      <!-- Botão -->
      <button data-go="grade_oficinas" class="w-full bg-primary hover:bg-primary/90 text-white font-bold text-lg py-4 rounded-xl shadow-lg shadow-primary/20 transition-all mt-6 active:scale-[0.98]" type="submit">
        Criar conta
      </button>
    </form>

    <div class="mt-auto py-8 text-center">
      <p class="text-slate-600 dark:text-slate-400 text-sm">
        Já possui uma conta?
        <a data-go="tela_login" class="text-primary font-bold hover:underline ml-1 cursor-pointer">Faça login</a>
      </p>
    </div>
    <div class="mt-4 flex justify-center gap-1 opacity-20">
      <div class="size-2 rounded-full bg-primary"></div>
      <div class="size-2 rounded-full bg-primary"></div>
      <div class="size-2 rounded-full bg-primary/40"></div>
    </div>
  </main>

  <footer class="w-full max-w-[480px] p-4 text-center">
    <div class="flex items-center justify-center gap-2 text-slate-400 text-xs">
      <span class="material-symbols-outlined text-sm">verified_user</span>
      <span>Ambiente seguro e criptografado</span>
    </div>
  </footer>
</div>
