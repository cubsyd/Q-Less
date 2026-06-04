import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, RouterModule } from '@angular/router';
import { AuthService } from '../../services/auth.js';

@Component({
  selector: 'app-register',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './register.html',
  styleUrl: './register.css'
})
export class RegisterComponent {

  user = {
    name: '',
    email: '',
    telefono: '',
    password: '',
    password_confirmation: ''
  };

  errors: { type: string; message: string }[] = [];
  isLoading = false;

  constructor(
    private authService: AuthService,
    private router: Router
  ) {}

  clearErrors() {
    this.errors = [];
  }

  addError(type: string, message: string) {
    this.errors.push({ type, message });
  }

  isValidEmail(email: string): boolean {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
  }

  validateForm(): boolean {

    this.clearErrors();

    let isValid = true;

    if (!this.user.name.trim()) {
      this.addError('danger', 'El nombre es requerido');
      isValid = false;
    }

    if (!this.user.email.trim()) {

      this.addError('danger', 'El email es requerido');

      isValid = false;

    } else if (!this.isValidEmail(this.user.email)) {

      this.addError('danger', 'Formato de email invalido');

      isValid = false;
    }

    if (!this.user.telefono.trim()) {

      this.addError('danger', 'El telefono es requerido');

      isValid = false;
    }

    const password = this.user.password;

    if (password.length < 8) {

      this.addError(
        'danger',
        'La contrasena debe tener minimo 8 caracteres'
      );

      isValid = false;
    }

    if (!/[A-Z]/.test(password)) {

      this.addError(
        'danger',
        'La contrasena debe incluir al menos una mayuscula'
      );

      isValid = false;
    }

    if (!/[a-z]/.test(password)) {

      this.addError(
        'danger',
        'La contrasena debe incluir al menos una minuscula'
      );

      isValid = false;
    }

    if (!/[0-9]/.test(password)) {

      this.addError(
        'danger',
        'La contrasena debe incluir al menos un numero'
      );

      isValid = false;
    }

    if (!/[@$!%*?&]/.test(password)) {

      this.addError(
        'danger',
        'La contrasena debe incluir al menos un simbolo especial'
      );

      isValid = false;
    }

    if (
      this.user.password !==
      this.user.password_confirmation
    ) {

      this.addError(
        'danger',
        'Las contrasenas no coinciden'
      );

      isValid = false;
    }

    return isValid;
  }

  registrar(): void {

    if (!this.validateForm()) return;

    this.isLoading = true;

    this.clearErrors();

    this.authService.register(this.user).subscribe({

      next: (res: any) => {

        this.isLoading = false;

        if (res?.status) {
          this.addError(
            res?.email_sent === false ? 'warning' : 'success',
            res?.email_sent === false
              ? 'Cuenta creada, pero no se pudo enviar el correo de verificacion. Intenta reenviarlo desde el login.'
              : 'Cuenta creada. Revisa tu correo y abre el enlace de verificacion antes de iniciar sesion.'
          );

          setTimeout(() => {
            this.router.navigate(['/login'], {
              queryParams: { verification: 'sent', email: this.user.email }
            });
          }, 3500);

        } else {
          this.addError(
            'danger',
            'La respuesta del servidor no fue valida.'
          );
        }
      },

      error: (err: any) => {

        this.isLoading = false;

        const serverErrors = err?.error?.errors;

        this.addError(
          'danger',
          serverErrors?.email?.[0]
          || serverErrors?.telefono?.[0]
          || serverErrors?.password?.[0]
          || 'Error de conexion con el servidor.'
        );
      }
    });
  }
}
