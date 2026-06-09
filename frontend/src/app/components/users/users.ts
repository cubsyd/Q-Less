import { ChangeDetectorRef, Component, OnInit } from '@angular/core';
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
export class UsersComponent implements OnInit {

  users: any[] = [];
  isLoading = false;
  message = '';
  deleting = new Set<number>();
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
  }

  loadUsers(retry = 0): void {
    this.isLoading = true;
    this.message = 'Cargando usuarios...';

    this.http.get<any>(this.usersUrl).subscribe({
      next: (res) => {
        this.users = res.users || [];
        this.isLoading = false;
        this.message = this.users.length ? '' : 'No hay usuarios.';
        this.cdr.detectChanges();
      },
      error: (err) => {
        console.error('Error cargando usuarios', err);
        if (retry < 3) {
          setTimeout(() => this.loadUsers(retry + 1), 800);
          return;
        }
        this.isLoading = false;
        this.message = 'No se pudieron cargar los usuarios.';
        this.cdr.detectChanges();
      }
    });
  }

  deleteUser(user: any): void {
    if (!confirm('Eliminar usuario "' + user.name + '"?')) {
      return;
    }

    this.deleting.add(user.id);
    this.users = this.users.filter(u => u.id !== user.id);
    this.cdr.detectChanges();

    this.http.delete(`${this.usersUrl}/${user.id}`).subscribe({
      next: () => {
        this.deleting.delete(user.id);
      },
      error: (err) => {
        console.error('Error eliminando usuario', err);
        this.deleting.delete(user.id);
        this.message = 'El usuario fue removido de la lista localmente pero no del servidor.';
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
