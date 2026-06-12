import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute } from '@angular/router';
import { RouterModule } from '@angular/router';
import { AuthService } from '../../services/auth.js';

@Component({
  selector: 'app-email-verified',
  standalone: true,
  imports: [CommonModule, RouterModule],
  templateUrl: './email-verified.html',
  styleUrls: ['./email-verified.css']
})
export class EmailVerifiedComponent {
  status = 'success';
  email = '';
  emailSent = true;
  emailError = '';
  resendMessage = '';
  isResending = false;

  constructor(
    private route: ActivatedRoute,
    private authService: AuthService
  ) {
    this.status = this.route.snapshot.queryParamMap.get('status') || 'success';
    this.email = this.route.snapshot.queryParamMap.get('email') || '';
    this.emailSent = this.route.snapshot.queryParamMap.get('sent') !== '0';
    this.emailError = this.route.snapshot.queryParamMap.get('error') || '';
  }

  get isInvalid(): boolean {
    return this.status === 'invalid';
  }

  get isPending(): boolean {
    return this.status === 'pending';
  }

  get title(): string {
    if (this.isPending) {
      return 'Revisa tu correo';
    }

    return this.isInvalid ? 'Enlace invalido' : 'Correo verificado';
  }

  get description(): string {
    if (this.isPending) {
      return this.emailSent
        ? 'Te enviamos un enlace de verificacion. Abre tu correo y confirma la cuenta para poder iniciar sesion.'
        : 'Tu cuenta fue creada, pero no pudimos enviar el correo de verificacion. Puedes intentarlo nuevamente.';
    }

    return this.isInvalid
      ? 'El enlace no es valido, ya fue usado o expiro.'
      : 'Tu cuenta ha sido verificada correctamente. Ya puedes iniciar sesion en Q-LESS.';
  }

  resendVerification(): void {
    if (!this.email || this.isResending) {
      return;
    }

    this.isResending = true;
    this.resendMessage = '';

    this.authService.resendVerificationEmail(this.email).subscribe({
      next: (response: any) => {
        this.isResending = false;
        this.emailSent = !!response?.email_sent;
        this.emailError = response?.email_error || '';
        this.resendMessage = this.emailSent
          ? response?.message || 'Correo de verificacion enviado.'
          : this.emailError || response?.message || 'No se pudo reenviar el correo.';
      },
      error: (error: any) => {
        this.isResending = false;
        this.emailError = error?.error?.email_error || '';
        this.resendMessage = this.emailError || error?.error?.message || error?.message || 'No se pudo reenviar el correo.';
      }
    });
  }
}
