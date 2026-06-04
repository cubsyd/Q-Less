import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { tap } from 'rxjs/operators';
import { environment } from '../../environments/environment';

@Injectable({
  providedIn: 'root'
})
export class AuthService {
  private API_URL = environment.apiBaseUrl;

  constructor(private http: HttpClient) { }

  register(userData: any): Observable<any> {
    return this.http.post(`${this.API_URL}/register`, userData);
  }

  login(credentials: any): Observable<any> {
    return this.http.post(`${this.API_URL}/login`, credentials);
  }

  resendVerificationEmail(email: string): Observable<any> {
    return this.http.post(`${this.API_URL}/email/resend`, { email });
  }

  saveToken(token: string): void {
    localStorage.setItem('auth_token', token);
  }

  saveUserRole(role: string): void {
    localStorage.setItem('user_role', role);
  }

  getToken(): string | null {
    return localStorage.getItem('auth_token');
  }

  getUserRole(): string {
    return localStorage.getItem('user_role') || 'usuario';
  }

  isAdmin(): boolean {
    return this.getUserRole() === 'admin';
  }

  clearSession(): void {
    localStorage.removeItem('auth_token');
    localStorage.removeItem('user_name');
    localStorage.removeItem('user_id');
    localStorage.removeItem('user_role');
  }

  logout(): Observable<any> {
    return this.http.post(`${this.API_URL}/logout`, {}).pipe(
      tap(() => {
        this.clearSession();
      })
    );
  }
}
