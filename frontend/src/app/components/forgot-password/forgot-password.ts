import { CommonModule } from '@angular/common';
import { Component } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { AuthService } from '../../services/auth.js';

@Component({
  selector: 'app-forgot-password',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './forgot-password.html',
  styleUrls: ['./forgot-password.css']
})
export class ForgotPasswordComponent {
  email = '';
  isLoading = false;
  alerts: { type: string; message: string }[] = [];

  constructor(private authService: AuthService) {}

  isValidEmail(email: string): boolean {
    return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email);
  }

  setAlert(type: string, message: string): void {
    this.alerts = [{ type, message }];
  }

  sendResetLink(): void {
    const email = this.email.trim().toLowerCase();
    this.alerts = [];

    if (!email) {
      this.setAlert('danger', 'Ingresa el correo asociado a tu cuenta.');
      return;
    }

    if (!this.isValidEmail(email)) {
      this.setAlert('warning', 'Ingresa un correo valido. Ejemplo: usuario@dominio.com');
      return;
    }

    this.isLoading = true;

    this.authService.forgotPassword({ email }).subscribe({
      next: (response: any) => {
        this.isLoading = false;
        this.setAlert('success', response?.message || 'Revisa tu correo para restablecer la contrasena.');
      },
      error: (error: any) => {
        this.isLoading = false;

        if (error?.status === 404) {
          this.setAlert('danger', 'No existe una cuenta registrada con ese correo.');
          return;
        }

        if (error?.status === 0) {
          this.setAlert('warning', 'No se pudo conectar al servidor. Verifica tu internet.');
          return;
        }

        this.setAlert(
          'danger',
          error?.error?.message || 'No se pudo enviar el correo de recuperacion. Intenta de nuevo.'
        );
      }
    });
  }
}
