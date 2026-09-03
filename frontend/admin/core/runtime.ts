/**
 * Registro compartilhado durante a migração incremental do dashboard.
 *
 * Os contratos estáveis vivem em interfaces próprias. O valor dinâmico fica
 * restrito a esta fronteira para permitir que os módulos preservem o código
 * legado sem criar dependências globais em `window`.
 */
export type RuntimeValue = any;

export interface AdminRuntime {
  [name: string]: RuntimeValue;
}

export type AdminModuleInitializer = (runtime: AdminRuntime) => void;

export function createAdminRuntime(): AdminRuntime {
  return Object.create(null) as AdminRuntime;
}

