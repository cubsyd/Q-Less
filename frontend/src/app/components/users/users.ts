import { ChangeDetectorRef, Component, OnDestroy, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { Router, RouterModule } from '@angular/router';
import { AuthService } from '../../services/auth.js';
import { environment } from '../../../environments/environment';

@Component({
  selector: 'app-users',
  standalone: true,
  imports: [CommonModule, RouterModule],
  templateUrl: './users.html',
  styleUrls: ['./users.css']
})
export class UsersComponent implements OnInit, OnDestroy {

  users: any[] = [];
  isLoading = false;
  message = '';
  deleting = new Set<number>();
  private usersRefreshIntervalId: number | null = null;
  private readonly usersUrl = `${environment.apiBaseUrl}/users`;

  constructor(
    private http: HttpClient,
    private authService: AuthService,
    private router: Router,
    private cdr: ChangeDetectorRef
  ) { }

  ngOnInit(): void {
    if (!this.authService.isAdmin()) {
      this.router.navigate(['/home']);
      return;
    }

    this.loadUsers();
    this.usersRefreshIntervalId = window.setInterval(() => {
      this.loadUsers(0, true);
    }, 5000);
  }

  ngOnDestroy(): void {
    if (this.usersRefreshIntervalId !== null) {
      window.clearInterval(this.usersRefreshIntervalId);
    }
  }

  loadUsers(retry = 0, silent = false): void {
    this.isLoading = true;
    if (!silent) {
      this.message = 'Cargando usuarios...';
    }

    this.http.get<any>(this.usersUrl).subscribe({
      next: (res) => {
        this.users = res.users || [];
        this.isLoading = false;
        if (!silent || !this.users.length) {
          this.message = this.users.length ? '' : 'No hay usuarios.';
        }
        this.cdr.detectChanges();
      },
      error: (err) => {
        console.error('Error cargando usuarios', err);
        if (retry < 3) {
          setTimeout(() => this.loadUsers(retry + 1, silent), 800);
          return;
        }
        this.isLoading = false;
        if (!silent) {
          this.message = 'No se pudieron cargar los usuarios.';
        }
        this.cdr.detectChanges();
      }
    });
  }

  getUserPhotoUrl(user: any): string | null {
    if (!user?.profile_photo_url) {
      return null;
    }

    const separator = String(user.profile_photo_url).includes('?') ? '&' : '?';
    const version = encodeURIComponent(user.updated_at || Date.now());

    return `${user.profile_photo_url}${separator}panel_v=${version}`;
  }

  deleteUser(user: any): void {
    if (!confirm('Eliminar usuario "' + user.name + '"?')) {
      return;
    }

    this.deleting.add(user.id);
    this.cdr.detectChanges();

    this.http.delete(`${this.usersUrl}/${user.id}`).subscribe({
      next: (res: any) => {
        this.users = this.users.filter(u => u.id !== user.id);
        this.deleting.delete(user.id);
        this.message = res?.message || 'Usuario eliminado correctamente.';
        this.cdr.detectChanges();
      },
      error: (err) => {
        console.error('Error eliminando usuario', err);
        this.deleting.delete(user.id);
        this.message = err?.error?.message || 'No se pudo eliminar el usuario.';
        this.cdr.detectChanges();
      }
    });
  }

  logout(): void {
    this.authService.logout().subscribe({
      next: () => {
        this.router.navigate(['/login']);
      },
      error: () => {
        this.authService.clearSession();
        this.router.navigate(['/login']);
      }
    });
  }

}
