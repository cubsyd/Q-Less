import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule, Router } from '@angular/router';
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
  showPassword = false;

  constructor(
    private authService: AuthService,
    private router: Router
  ) {}

  clearErrors(): void {
    this.errors = [];
  }

  addError(type: string, message: string): void {
    this.errors.push({ type, message });
  }

  setError(type: string, message: string): void {
    this.errors = [{ type, message }];
  }

  validateForm(): boolean {
    this.clearErrors();
    let isValid = true;
    const email = this.credentials.email.trim();
    const password = this.credentials.password;

    if (!email) {
      this.addError('danger', 'El email es requerido para iniciar sesion');
      isValid = false;
    } else if (!this.isValidEmail(email)) {
      this.addError('warning', 'El email no tiene un formato valido. Usa: usuario@dominio.com');
      isValid = false;
    }

    if (!password.trim()) {
      this.addError('danger', 'La contrasena es requerida');
      isValid = false;
    } else if (password.length < 8) {
      this.addError('danger', 'La contrasena debe tener minimo 8 caracteres');
      isValid = false;
    } else if (/\s/.test(password)) {
      this.addError('danger', 'La contrasena no debe contener espacios');
      isValid = false;
    }

    return isValid;
  }

  isValidEmail(email: string): boolean {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
    return emailRegex.test(email);
  }

  togglePasswordVisibility(): void {
    this.showPassword = !this.showPassword;
  }

  flattenServerErrors(errors: any): string[] {
    if (!errors) {
      return [];
    }

    if (typeof errors === 'string') {
      return [errors];
    }

    if (Array.isArray(errors)) {
      return errors.flatMap((item) => this.flattenServerErrors(item));
    }

    if (typeof errors === 'object') {
      return Object.values(errors).flatMap((item) => this.flattenServerErrors(item));
    }

    return [String(errors)];
  }

  getServerErrorMessages(error: any): string[] {
    if (typeof error?.error === 'string' && error.error.trim()) {
      return [error.error.trim()];
    }

    const serverErrors = error?.error?.errors;
    const messages = this.flattenServerErrors(serverErrors);

    if (messages.length > 0) {
      return messages.map(String).filter(Boolean);
    }

    const serverMessage = error?.error?.message || error?.message || '';
    return typeof serverMessage === 'string' && serverMessage.trim()
      ? [serverMessage]
      : [];
  }

  isEmailNotRegisteredError(error: any): boolean {
    const message = String(
      error?.error?.message ||
      (typeof error?.error === 'string' ? error.error : '') ||
      error?.message ||
      ''
    ).toLowerCase();

    return (
      error?.status === 404 ||
      message.includes('usuario no existe') ||
      message.includes('user not found') ||
      message.includes('no esta registrado') ||
      message.includes('no está registrado') ||
      message.includes('email no existe') ||
      message.includes('correo no existe') ||
      message.includes('correo no registrado')
    );
  }

  iniciarSesion(): void {
    if (!this.validateForm() || this.isLoading) {
      return;
    }

    this.isLoading = true;
    this.clearErrors();

    const payload = {
      email: this.credentials.email.trim().toLowerCase(),
      password: this.credentials.password
    };

    this.authService.login(payload).subscribe({
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
          const code = String(res?.code || '').toUpperCase();
          const message = String(res?.message || '');

          if (code === 'USER_NOT_FOUND' || message.toLowerCase().includes('usuario no existe')) {
            this.setError('danger', 'El correo ingresado no esta registrado. Verifica el email o crea una cuenta nueva.');
          } else {
            this.setError('danger', message || 'Error en la respuesta del servidor. Intenta de nuevo.');
          }
        }
      },
      error: (err: any) => {
        this.isLoading = false;

        if (this.isEmailNotRegisteredError(err)) {
          this.setError('danger', 'El correo ingresado no esta registrado. Verifica el email o crea una cuenta nueva.');
          return;
        }

        if (err?.status === 401) {
          this.setError('danger', 'Email o contrasena incorrectos. Verifica tus credenciales.');
        } else if (err?.status === 422) {
          const messages = this.getServerErrorMessages(err);
          if (messages.length > 0) {
            this.errors = messages.map((message) => ({ type: 'danger', message }));
          } else {
            this.setError('danger', 'Email o contrasena incorrectos. Intenta de nuevo.');
          }
        } else if (err?.status === 429) {
          this.setError('danger', 'Demasiados intentos fallidos. Intenta en unos minutos.');
        } else if (err?.status === 0) {
          this.setError('danger', 'No se pudo conectar al servidor. Verifica tu conexion de internet.');
        } else if (err?.status === 500) {
          this.setError('danger', 'Error del servidor. Intenta mas tarde.');
        } else {
          const messages = this.getServerErrorMessages(err);
          if (messages.length > 0) {
            this.errors = messages.map((message) => ({ type: 'danger', message }));
          } else {
            this.setError('danger', 'Error al iniciar sesion. Intenta de nuevo.');
          }
        }
      }
    });
  }
}
