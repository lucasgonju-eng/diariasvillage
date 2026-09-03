export interface AdminStudent {
  id: string;
  name: string;
  enrollment?: string | null;
  grade?: number | string | null;
  class_name?: string | null;
}

export interface AdminGuardian {
  id: string;
  parent_name?: string | null;
  email?: string | null;
  parent_phone?: string | null;
  parent_document?: string | null;
}

export interface ApiEnvelope {
  ok: boolean;
  error?: string;
  code?: string;
  [key: string]: unknown;
}

export interface ResolvedStudentIdentity {
  ok: true;
  student: AdminStudent;
  id: string;
  name: string;
  label: string;
}

export interface StudentIdentityError {
  ok: false;
  error: string;
}

export type StudentIdentityResolution =
  | ResolvedStudentIdentity
  | StudentIdentityError;

declare global {
  interface Window {
    __adminCanApproveAttendance?: boolean;
    __adminDashboardBooted?: boolean;
    __adminStudents?: AdminStudent[];
    __monthlyStudents?: unknown[];
  }
}

