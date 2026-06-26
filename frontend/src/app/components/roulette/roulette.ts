import { ChangeDetectorRef, Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router, RouterModule } from '@angular/router';
import { AuthService } from '../../services/auth.js';
import { RouletteReward, RouletteService } from '../../services/roulette.service';

@Component({
  selector: 'app-roulette',
  standalone: true,
  imports: [CommonModule, RouterModule],
  templateUrl: './roulette.html',
  styleUrls: ['./roulette.css']
})
export class RouletteComponent implements OnInit {
  activeReward: RouletteReward | null = null;
  message = '';
  isSpinning = false;
  rotation = 0;
  userPhotoUrl: string | null = null;

  readonly prizes = [
    '10% descuento',
    '2x1',
    '15% descuento',
    'Intentalo luego',
    '10% descuento',
    '20% descuento',
  ];

  constructor(
    private authService: AuthService,
    private rouletteService: RouletteService,
    private router: Router,
    private cdr: ChangeDetectorRef
  ) {}

  ngOnInit(): void {
    if (this.authService.isAdmin()) {
      this.router.navigate(['/productos']);
      return;
    }

    this.loadActiveReward();
    this.loadNavProfile();
  }

  spin(): void {
    if (this.isSpinning || this.activeReward) {
      return;
    }

    this.isSpinning = true;
    this.message = 'Girando ruleta...';
    this.rotation += 1440 + Math.floor(Math.random() * 360);
    this.cdr.detectChanges();

    window.setTimeout(() => {
      this.rouletteService.spin().subscribe({
        next: (response) => {
          this.activeReward = response.reward;
          this.message = response.reward.prize_type === 'no_prize'
            ? 'Esta vez no hubo premio. Podras volver a girar en 10 minutos.'
            : `Ganaste ${response.reward.label}. Se aplicara automaticamente en el carrito y al pagar.`;
          this.isSpinning = false;
          this.cdr.detectChanges();
        },
        error: (error) => {
          this.activeReward = null;
          this.message = error?.error?.errors?.ruleta?.[0]
            || error?.error?.message
            || 'No se pudo girar la ruleta en este momento.';
          this.isSpinning = false;
          this.loadActiveReward();
          this.cdr.detectChanges();
        }
      });
    }, 1600);
  }

  getRewardExpiry(): string {
    if (!this.activeReward?.expires_at) {
      return '10 minutos';
    }

    const date = new Date(this.activeReward.expires_at);

    if (Number.isNaN(date.getTime())) {
      return '10 minutos';
    }

    return date.toLocaleString('es-CO', {
      day: '2-digit',
      month: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
    });
  }

  logout(): void {
    this.authService.logout().subscribe({
      next: () => this.router.navigate(['/login']),
      error: () => {
        this.authService.clearSession();
        this.router.navigate(['/login']);
      }
    });
  }

  getNavInitials(): string {
    return this.authService.getUserInitials('U');
  }

  private loadActiveReward(): void {
    this.rouletteService.getActiveReward().subscribe({
      next: (response) => {
        this.activeReward = response.reward;
        this.message = response.reward
          ? response.reward.prize_type === 'no_prize'
            ? 'Ya giraste hace poco. Podras intentarlo otra vez cuando termine el tiempo de espera.'
            : `Tienes activo: ${response.reward.label}. Puedes usarlo en tu proximo pedido.`
          : 'Gira una vez y usa tu premio en el carrito.';
        this.cdr.detectChanges();
      },
      error: () => {
        this.message = 'No se pudo consultar tu premio activo.';
        this.cdr.detectChanges();
      }
    });
  }

  private loadNavProfile(): void {
    this.userPhotoUrl = this.authService.getUserPhotoUrl();

    this.authService.loadCurrentUser().subscribe({
      next: (response) => {
        this.userPhotoUrl = response?.user?.profile_photo_url || this.authService.getUserPhotoUrl();
        this.cdr.detectChanges();
      },
      error: () => this.cdr.detectChanges()
    });
  }
}
