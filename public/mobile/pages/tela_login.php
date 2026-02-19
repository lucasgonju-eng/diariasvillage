<!-- Login - Diárias Village -->
<div class="relative flex h-full min-h-screen w-full flex-col bg-background-light dark:bg-background-dark group/design-root overflow-x-hidden">
  <!-- Top App Bar -->
  <header class="flex items-center bg-transparent p-4 pb-2 justify-between w-full max-w-[480px] mx-auto">
    <div data-go="tela_inicial" class="text-primary dark:text-slate-100 flex size-12 shrink-0 items-center justify-start cursor-pointer">
      <span class="material-symbols-outlined text-[28px]">arrow_back</span>
    </div>
    <div class="flex-1 flex justify-center pr-12">
      <span class="text-primary font-bold text-xl tracking-tight">Diárias Village</span>
    </div>
  </header>

  <!-- Content Section -->
  <main class="flex-1 flex flex-col justify-center items-center px-6 w-full max-w-[480px] mx-auto pb-12">
    <div class="mb-8 flex flex-col items-center">
      <div class="w-20 h-20 bg-primary/10 dark:bg-primary/20 rounded-2xl flex items-center justify-center mb-6">
        <span class="material-symbols-outlined text-primary text-5xl">holiday_village</span>
      </div>
      <h2 class="text-slate-900 dark:text-slate-100 tracking-tight text-[32px] font-bold leading-tight text-center">
        Bem-vindo de volta
      </h2>
      <p class="text-slate-500 dark:text-slate-400 text-base font-normal leading-normal mt-2 text-center">
        Acesse sua conta para gerenciar suas diárias
      </p>
    </div>

    <form class="w-full space-y-5" onsubmit="return false;">
      <!-- CPF -->
      <div class="flex flex-col w-full">
        <label class="text-slate-700 dark:text-slate-300 text-sm font-semibold leading-normal pb-2 ml-1">CPF</label>
        <div class="relative group">
          <input class="form-input flex w-full min-w-0 flex-1 resize-none overflow-hidden rounded-xl text-slate-900 dark:text-slate-100 focus:outline-0 focus:ring-2 focus:ring-primary/50 border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 h-14 placeholder:text-slate-400 p-[15px] text-base font-normal leading-normal transition-all" inputmode="numeric" placeholder="000.000.000-00" type="text"/>
          <div class="absolute right-4 top-4 text-slate-400">
            <span class="material-symbols-outlined">badge</span>
          </div>
        </div>
      </div>
      <!-- Senha -->
      <div class="flex flex-col w-full">
        <label class="text-slate-700 dark:text-slate-300 text-sm font-semibold leading-normal pb-2 ml-1">Senha</label>
        <div class="flex w-full flex-1 items-stretch rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 overflow-hidden focus-within:ring-2 focus-within:ring-primary/50 transition-all">
          <input class="form-input flex w-full min-w-0 flex-1 resize-none border-none bg-transparent h-14 text-slate-900 dark:text-slate-100 placeholder:text-slate-400 p-[15px] text-base font-normal leading-normal focus:ring-0" placeholder="••••••••" type="password"/>
          <button class="text-slate-400 hover:text-primary flex items-center justify-center px-4 transition-colors" type="button">
            <span class="material-symbols-outlined">visibility</span>
          </button>
        </div>
        <div class="flex justify-end mt-2">
          <a class="text-primary dark:text-primary/80 text-sm font-medium hover:underline" href="#">Esqueci minha senha</a>
        </div>
      </div>
      <!-- Botão -->
      <div class="pt-6">
        <button data-go="grade_oficinas" class="w-full bg-primary hover:bg-primary/90 text-white font-bold h-14 rounded-xl flex items-center justify-center shadow-lg shadow-primary/20 active:scale-[0.98] transition-all">
          <span class="mr-2">Entrar</span>
          <span class="material-symbols-outlined">login</span>
        </button>
      </div>
    </form>

    <div class="mt-10 text-center w-full">
      <p class="text-slate-500 dark:text-slate-400 text-sm">
        Ainda não tem uma conta?
        <a data-go="tela_cadastro" class="text-primary dark:text-primary/80 font-bold hover:underline ml-1 cursor-pointer">Cadastre-se</a>
      </p>
    </div>

    <div class="mt-auto pt-10 opacity-30">
      <div class="h-1 w-20 bg-primary/20 rounded-full mx-auto"></div>
    </div>
  </main>
  <div class="h-8"></div>
</div>
