import { ChangeDetectorRef, Component, OnDestroy, OnInit } from '@angular/core';
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
export class RouletteComponent implements OnInit, OnDestroy {
  activeReward: RouletteReward | null = null;
  remainingSeconds = 0;
  message = '';
  isSpinning = false;
  rotation = 0;
  userPhotoUrl: string | null = null;
  private countdownIntervalId: number | null = null;

  readonly prizes = [
    { label: '10% descuento', className: 'label-top' },
    { label: '2x1', className: 'label-top-right' },
    { label: 'Intentalo despues', className: 'label-right' },
    { label: '10% descuento', className: 'label-bottom-right' },
    { label: 'Intentalo despues', className: 'label-bottom' },
    { label: '15% descuento', className: 'label-bottom-left' },
    { label: 'Intentalo despues', className: 'label-left' },
    { label: '20% descuento', className: 'label-top-left' },
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

  ngOnDestroy(): void {
    this.stopCountdown();
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
          this.startCountdown();
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
    if (this.remainingSeconds <= 0) {
      return '0:00';
    }

    const minutes = Math.floor(this.remainingSeconds / 60);
    const seconds = this.remainingSeconds % 60;

    return `${minutes}:${String(seconds).padStart(2, '0')}`;
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
        this.startCountdown();
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

  private startCountdown(): void {
    this.stopCountdown();

    if (!this.activeReward) {
      this.remainingSeconds = 0;
      return;
    }

    this.remainingSeconds = this.resolveRemainingSeconds(this.activeReward);

    this.countdownIntervalId = window.setInterval(() => {
      this.remainingSeconds = Math.max(0, this.remainingSeconds - 1);

      if (this.remainingSeconds === 0) {
        const expiredWasNoPrize = this.activeReward?.prize_type === 'no_prize';
        this.activeReward = null;
        this.message = expiredWasNoPrize
          ? 'Ya puedes volver a girar la ruleta.'
          : 'Tu cupon expiro. Los precios volvieron a su valor normal.';
        this.stopCountdown();
      }

      this.cdr.detectChanges();
    }, 1000);
  }

  private stopCountdown(): void {
    if (this.countdownIntervalId !== null) {
      window.clearInterval(this.countdownIntervalId);
      this.countdownIntervalId = null;
    }
  }

  private resolveRemainingSeconds(reward: RouletteReward): number {
    if (Number.isFinite(reward.remaining_seconds)) {
      return Math.max(0, Number(reward.remaining_seconds));
    }

    if (!reward.expires_at) {
      return 600;
    }

    const expiresAt = new Date(reward.expires_at).getTime();

    if (Number.isNaN(expiresAt)) {
      return 600;
    }

    return Math.max(0, Math.floor((expiresAt - Date.now()) / 1000));
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
