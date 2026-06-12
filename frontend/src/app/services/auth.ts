import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { tap, timeout } from 'rxjs/operators';
import { environment } from '../../environments/environment';

@Injectable({
  providedIn: 'root'
})
export class AuthService {
  private API_URL = environment.apiBaseUrl;

  constructor(private http: HttpClient) { }

  register(userData: any): Observable<any> {
    return this.http.post(`${this.API_URL}/register`, userData).pipe(timeout(30000));
  }

  login(credentials: any): Observable<any> {
    return this.http.post(`${this.API_URL}/login`, credentials).pipe(timeout(30000));
  }

  resendVerificationEmail(email: string): Observable<any> {
    return this.http.post(`${this.API_URL}/email/resend`, { email }).pipe(timeout(30000));
  }

  loadCurrentUser(): Observable<any> {
    const userId = this.getUserId();
    return this.http.get<any>(`${this.API_URL}/users/${userId}`).pipe(
      tap((response) => {
        const user = response?.user;
        if (user) {
          this.saveUserProfile(user);
        }
      })
    );
  }

  saveToken(token: string): void {
    sessionStorage.setItem('auth_token', token);
    localStorage.removeItem('auth_token');
  }

  saveUserRole(role: string): void {
    sessionStorage.setItem('user_role', role);
    localStorage.removeItem('user_role');
  }

  getToken(): string | null {
    localStorage.removeItem('auth_token');
    return sessionStorage.getItem('auth_token');
  }

  getUserRole(): string {
    localStorage.removeItem('user_role');
    return sessionStorage.getItem('user_role') || 'usuario';
  }

  getUserId(): string | null {
    localStorage.removeItem('user_id');
    return sessionStorage.getItem('user_id');
  }

  saveUserSession(user: any, role: string): void {
    this.saveUserProfile(user);
    this.saveUserRole(role);
  }

  saveUserProfile(user: any): void {
    if (user?.name) {
      sessionStorage.setItem('user_name', user.name);
    }

    if (user?.id) {
      sessionStorage.setItem('user_id', String(user.id));
    }

    if (user?.profile_photo_url) {
      sessionStorage.setItem('user_photo_url', user.profile_photo_url);
    }

    if (user?.rol) {
      sessionStorage.setItem('user_role', user.rol);
    }

    localStorage.removeItem('user_name');
    localStorage.removeItem('user_id');
    localStorage.removeItem('user_photo_url');
  }

  getUserName(defaultName = 'Aprendiz'): string {
    localStorage.removeItem('user_name');
    return sessionStorage.getItem('user_name') || defaultName;
  }

  saveUserName(name: string): void {
    sessionStorage.setItem('user_name', name);
    localStorage.removeItem('user_name');
  }

  saveUserPhotoUrl(photoUrl: string | null): void {
    if (photoUrl) {
      sessionStorage.setItem('user_photo_url', photoUrl);
    } else {
      sessionStorage.removeItem('user_photo_url');
    }

    localStorage.removeItem('user_photo_url');
  }

  getUserPhotoUrl(): string | null {
    localStorage.removeItem('user_photo_url');
    return sessionStorage.getItem('user_photo_url');
  }

  getUserInitials(defaultInitials = 'U'): string {
    const name = this.getUserName(defaultInitials).trim();
    return name
      .split(/\s+/)
      .slice(0, 2)
      .map(part => part.charAt(0).toUpperCase())
      .join('') || defaultInitials;
  }

  getUserRoleLabel(): string {
    const role = this.getUserRole();

    if (role === 'admin') {
      return 'Administrador';
    }

    if (role === 'instructor') {
      return 'Instructor';
    }

    return 'Aprendiz';
  }

  isAdmin(): boolean {
    return this.getUserRole() === 'admin';
  }

  clearSession(): void {
    sessionStorage.removeItem('auth_token');
    sessionStorage.removeItem('user_name');
    sessionStorage.removeItem('user_id');
    sessionStorage.removeItem('user_role');
    sessionStorage.removeItem('user_photo_url');
    localStorage.removeItem('auth_token');
    localStorage.removeItem('user_name');
    localStorage.removeItem('user_id');
    localStorage.removeItem('user_role');
    localStorage.removeItem('user_photo_url');
  }

  logout(): Observable<any> {
    return this.http.post(`${this.API_URL}/logout`, {}).pipe(
      tap(() => {
        this.clearSession();
      })
    );
  }
}
