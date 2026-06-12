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
    rol: 'aprendiz',
    password: '',
    password_confirmation: ''
  };

  errors: { type: string; message: string }[] = [];
  isLoading = false;
  showPassword = false;
  showPasswordConfirmation = false;

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
    return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email);
  }

  hasValidName(name: string): boolean {
    return /^[\p{L}\s'-]+$/u.test(name);
  }

  get passwordRules() {
    const password = this.user.password;

    return [
      { label: '8+ chars', passed: password.length >= 8 },
      { label: 'Mayuscula', passed: /[A-Z]/.test(password) },
      { label: 'Minuscula', passed: /[a-z]/.test(password) },
      { label: 'Numero', passed: /[0-9]/.test(password) },
      { label: 'Especial', passed: /[@$!%*?&]/.test(password) },
    ];
  }

  get passwordScore(): number {
    return this.passwordRules.filter(rule => rule.passed).length;
  }

  get passwordStrengthClass(): string {
    if (this.passwordScore <= 2) {
      return 'weak';
    }

    if (this.passwordScore <= 4) {
      return 'good';
    }

    return 'strong';
  }

  get passwordStrengthLabel(): string {
    if (this.passwordScore <= 2) {
      return 'Debil';
    }

    if (this.passwordScore <= 4) {
      return 'Buena';
    }

    return 'Segura';
  }

  get passwordStrengthWidth(): string {
    return `${Math.max(12, this.passwordScore * 20)}%`;
  }

  togglePasswordVisibility(field: 'password' | 'confirmation'): void {
    if (field === 'password') {
      this.showPassword = !this.showPassword;
      return;
    }

    this.showPasswordConfirmation = !this.showPasswordConfirmation;
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

    const name = this.user.name.trim();
    const email = this.user.email.trim();

    if (!name) {
      this.addError('danger', 'El nombre es requerido');
      isValid = false;
    } else if (name.length < 3) {
      this.addError('danger', 'El nombre debe tener minimo 3 caracteres');
      isValid = false;
    } else if (!this.hasValidName(name)) {
      this.addError('danger', 'El nombre solo puede contener letras y espacios');
      isValid = false;
    }

    if (!email) {

      this.addError('danger', 'El email es requerido');

      isValid = false;

    } else if (!this.isValidEmail(email)) {

      this.addError('danger', 'Formato de email invalido. Usa usuario@dominio.com');

      isValid = false;
    }

    if (!['aprendiz', 'instructor'].includes(this.user.rol)) {
      this.addError('danger', 'Selecciona si eres aprendiz o instructor');
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
      rol: this.user.rol,
      password: this.user.password,
      password_confirmation: this.user.password_confirmation
    };

    this.authService.register(payload).subscribe({

      next: (res: any) => {

        this.isLoading = false;

        if (res?.status === true) {
          this.addError(
            'success',
            res?.message || 'Cuenta creada correctamente. Ya puedes iniciar sesion.'
          );

          setTimeout(() => {
            this.router.navigate(['/login']);
          }, 1500);

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

        if (err?.name === 'TimeoutError') {
          this.addError(
            'danger',
            'El servidor tardo demasiado en responder. Intenta de nuevo en unos minutos.'
          );
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
