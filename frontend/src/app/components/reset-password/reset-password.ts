import { CommonModule } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { AuthService } from '../../services/auth.js';

@Component({
  selector: 'app-reset-password',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './reset-password.html',
  styleUrls: ['./reset-password.css']
})
export class ResetPasswordComponent implements OnInit {
  email = '';
  token = '';
  password = '';
  passwordConfirmation = '';
  showPassword = false;
  showPasswordConfirmation = false;
  isLoading = false;
  alerts: { type: string; message: string }[] = [];

  constructor(
    private route: ActivatedRoute,
    private router: Router,
    private authService: AuthService
  ) {}

  ngOnInit(): void {
    this.email = this.route.snapshot.queryParamMap.get('email') || '';
    this.token = this.route.snapshot.queryParamMap.get('token') || '';

    if (!this.email || !this.token) {
      this.setAlert('danger', 'El enlace de recuperacion no es valido. Solicita uno nuevo.');
    }
  }

  get passwordRules() {
    return [
      { label: '8+ chars', passed: this.password.length >= 8 },
      { label: 'Mayuscula', passed: /[A-Z]/.test(this.password) },
      { label: 'Minuscula', passed: /[a-z]/.test(this.password) },
      { label: 'Numero', passed: /[0-9]/.test(this.password) },
      { label: 'Especial', passed: /[@$!%*?&]/.test(this.password) },
    ];
  }

  get passwordScore(): number {
    return this.passwordRules.filter(rule => rule.passed).length;
  }

  get passwordStrengthClass(): string {
    if (this.passwordScore <= 2) return 'weak';
    if (this.passwordScore <= 4) return 'good';
    return 'strong';
  }

  get passwordStrengthLabel(): string {
    if (this.passwordScore <= 2) return 'Debil';
    if (this.passwordScore <= 4) return 'Buena';
    return 'Segura';
  }

  get passwordStrengthWidth(): string {
    return `${Math.max(12, this.passwordScore * 20)}%`;
  }

  setAlert(type: string, message: string): void {
    this.alerts = [{ type, message }];
  }

  togglePasswordVisibility(field: 'password' | 'confirmation'): void {
    if (field === 'password') {
      this.showPassword = !this.showPassword;
      return;
    }

    this.showPasswordConfirmation = !this.showPasswordConfirmation;
  }

  validateForm(): boolean {
    this.alerts = [];

    if (!this.email || !this.token) {
      this.setAlert('danger', 'El enlace de recuperacion no es valido. Solicita uno nuevo.');
      return false;
    }

    if (this.passwordScore < 5) {
      this.setAlert('danger', 'La contrasena debe incluir mayuscula, minuscula, numero, simbolo especial y minimo 8 caracteres.');
      return false;
    }

    if (this.password !== this.passwordConfirmation) {
      this.setAlert('danger', 'Las contrasenas no coinciden.');
      return false;
    }

    return true;
  }

  resetPassword(): void {
    if (!this.validateForm() || this.isLoading) {
      return;
    }

    this.isLoading = true;

    this.authService.resetPassword({
      email: this.email,
      token: this.token,
      password: this.password,
      password_confirmation: this.passwordConfirmation
    }).subscribe({
      next: (response: any) => {
        this.isLoading = false;
        this.setAlert('success', response?.message || 'Contrasena actualizada correctamente.');

        setTimeout(() => {
          this.router.navigate(['/login']);
        }, 1800);
      },
      error: (error: any) => {
        this.isLoading = false;

        if (error?.status === 0) {
          this.setAlert('warning', 'No se pudo conectar al servidor. Verifica tu internet.');
          return;
        }

        this.setAlert(
          'danger',
          error?.error?.message || 'No se pudo restablecer la contrasena. Solicita un nuevo enlace.'
        );
      }
    });
  }
}
