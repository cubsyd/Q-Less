import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, RouterModule, Router } from '@angular/router';
import { AuthService } from '../../services/auth.js';

@Component({
  selector: 'app-login',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './login.html',
  styleUrls: ['./login.css']
})
export class LoginComponent {
  credentials = { email: '', password: '' };
  errors: { type: string; message: string }[] = [];
  isLoading = false;
  pendingVerificationEmail = '';

  constructor(
    private authService: AuthService,
    private router: Router,
    private route: ActivatedRoute
  ) {
    const verification = this.route.snapshot.queryParamMap.get('verification');
    const email = this.route.snapshot.queryParamMap.get('email') || '';

    if (verification === 'sent') {
      this.pendingVerificationEmail = email;
      this.credentials.email = email;
      this.addError('success', 'Te enviamos un correo de verificacion. Abre el enlace antes de iniciar sesion.');
    }
  }

  clearErrors(): void {
    this.errors = [];
  }

  addError(type: string, message: string): void {
    this.errors.push({ type, message });
  }

  validateForm(): boolean {
    this.clearErrors();
    let isValid = true;

    if (!this.credentials.email.trim()) {
      this.addError('danger', 'El email es requerido para iniciar sesion');
      isValid = false;
    } else if (!this.isValidEmail(this.credentials.email)) {
      this.addError('warning', 'El email no tiene un formato valido. Usa: usuario@dominio.com');
      isValid = false;
    }

    if (!this.credentials.password.trim()) {
      this.addError('danger', 'La contrasena es requerida');
      isValid = false;
    }

    return isValid;
  }

  isValidEmail(email: string): boolean {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
  }

  getServerErrorMessages(error: any): string[] {
    const serverErrors = error?.error?.errors;
    if (serverErrors) {
      return Object.values(serverErrors)
        .flat()
        .map(String)
        .filter(Boolean);
    }

    const serverMessage = error?.error?.message || error?.message || '';
    return typeof serverMessage === 'string' && serverMessage.trim()
      ? [serverMessage]
      : [];
  }

  isEmailNotRegisteredError(error: any): boolean {
    const message = String(error?.error?.message || error?.message || '').toLowerCase();
    return (
      error?.status === 404 ||
      message.includes('usuario no existe') ||
      message.includes('no esta registrado') ||
      message.includes('email no existe') ||
      message.includes('correo no existe')
    );
  }

  iniciarSesion(): void {
    if (!this.validateForm()) {
      return;
    }

    this.isLoading = true;
    this.clearErrors();

    this.authService.login(this.credentials).subscribe({
      next: (res: any) => {
        this.isLoading = false;
        if (res?.status === true && res?.token) {
          this.authService.saveUserSession(res.user, res?.role || res?.user?.rol || 'usuario');
          this.authService.saveToken(res.token);
          this.addError('success', 'Bienvenido! Iniciando sesion...');
          setTimeout(() => {
            this.router.navigate(['/home']);
          }, 1500);
        } else {
          this.addError('danger', 'Error en la respuesta del servidor. Intenta de nuevo.');
        }
      },
      error: (err: any) => {
        this.isLoading = false;
        console.error('Error:', err);

        if (this.isEmailNotRegisteredError(err)) {
          this.addError('danger', 'El email no está registrado. ¿Quieres crear una cuenta?');
          return;
        }

        if (err?.status === 401) {
          this.addError('danger', 'Email o contraseña incorrectos. Verifica tus credenciales.');
        } else if (err?.status === 403 && err?.error?.email_verification_required) {
          this.pendingVerificationEmail = this.credentials.email;
          this.addError('danger', err?.error?.message || 'Debes verificar tu correo antes de iniciar sesión.');
        } else if (err?.status === 422) {
          const messages = this.getServerErrorMessages(err);
          if (messages.length > 0) {
            messages.forEach((message) => this.addError('danger', message));
          } else {
            this.addError('danger', 'Email o contraseña incorrectos. Intenta de nuevo.');
          }
        } else if (err?.status === 429) {
          this.addError('danger', 'Demasiados intentos fallidos. Intenta en unos minutos.');
        } else if (err?.status === 0) {
          this.addError('danger', 'No se pudo conectar al servidor. Verifica tu conexión de internet.');
        } else if (err?.status === 500) {
          this.addError('danger', 'Error del servidor. Intenta más tarde.');
        } else {
          const messages = this.getServerErrorMessages(err);
          if (messages.length > 0) {
            messages.forEach((message) => this.addError('danger', message));
          } else {
            this.addError('danger', 'Error al iniciar sesión. Intenta de nuevo.');
          }
        }
      }
    });
  }

  resendVerificationEmail(): void {
    const email = (this.pendingVerificationEmail || this.credentials.email).trim();

    if (!email || this.isLoading) {
      return;
    }

    this.isLoading = true;
    this.clearErrors();

    this.authService.resendVerificationEmail(email).subscribe({
      next: (res: any) => {
        this.isLoading = false;
        this.pendingVerificationEmail = email;
        this.addError(res?.email_sent === false ? 'warning' : 'success', res?.message || 'Correo reenviado.');
      },
      error: (err: any) => {
        this.isLoading = false;
        this.addError('danger', err?.error?.message || 'No se pudo reenviar el correo de verificacion.');
      }
    });
  }
}
