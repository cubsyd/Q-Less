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
  styleUrls: ['./register.css']
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

  isValidPhone(phone: string): boolean {
    return /^[0-9+\s()\-]+$/.test(phone);
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

  isDuplicateEmailError(error: any): boolean {
    const messages = this.getServerErrorMessages(error).map((msg) => msg.toLowerCase());
    return messages.some((text) =>
      text.includes('ya esta registrado') ||
      text.includes('already been taken') ||
      text.includes('duplicate') ||
      text.includes('correo duplicado') ||
      text.includes('correo ya esta registrado')
    );
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
    } else if (!this.isValidPhone(this.user.telefono)) {
      this.addError(
        'danger',
        'El telefono solo puede contener numeros, espacios, parentesis, + o -.'
      );
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

    const payload = {
      name: this.user.name.trim(),
      email: this.user.email.trim().toLowerCase(),
      telefono: this.user.telefono.trim(),
      password: this.user.password,
      password_confirmation: this.user.password_confirmation
    };

    this.authService.register(payload).subscribe({

      next: (res: any) => {

        this.isLoading = false;

        if (res?.status === true) {
          this.addError(
            res?.email_sent === false ? 'warning' : 'success',
            res?.message || (
              res?.email_sent === false
                ? 'Cuenta creada, pero no se pudo enviar el correo de verificacion. Intenta reenviarlo desde el login.'
                : 'Cuenta creada. Revisa tu correo y abre el enlace de verificacion antes de iniciar sesion.'
            )
          );

          setTimeout(() => {
            this.router.navigate(['/login'], {
              queryParams: { verification: 'sent', email: payload.email }
            });
          }, 3500);

        } else if (res?.status === false && res?.message) {
          this.addError('danger', res.message);
        }
      },

      error: (err: any) => {

        this.isLoading = false;

        if (this.isDuplicateEmailError(err)) {
          this.addError(
            'danger',
            'Este correo ya está registrado. Inicia sesión con tu cuenta.'
          );
          return;
        }

        const messages = this.getServerErrorMessages(err);
        if (messages.length > 0) {
          messages.forEach((message) => {
            this.addError('danger', message);
          });
          return;
        }

        this.addError(
          'danger',
          err?.error?.message || 'Error de conexion con el servidor.'
        );
      }
    });
  }
}
