import { Component, ChangeDetectorRef, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { Router, RouterModule } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { AuthService } from '../../services/auth.js';
import { environment } from '../../../environments/environment';

@Component({
  selector: 'app-user-profile',
  standalone: true,
  imports: [CommonModule, RouterModule, FormsModule],
  templateUrl: './user-profile.html',
  styleUrls: ['./user-profile.css']
})
export class UserProfileComponent implements OnInit {
  user: any = { name: '', email: '', telefono: '', profile_photo_url: null };
  isLoading = false;
  message = '';
  private readonly usersUrl = `${environment.apiBaseUrl}/users`;

  constructor(
    private http: HttpClient,
    private authService: AuthService,
    private router: Router,
    private cdr: ChangeDetectorRef
  ) { }

  ngOnInit(): void {
    const userId = Number(localStorage.getItem('user_id'));
    if (!userId) {
      this.router.navigate(['/login']);
      return;
    }
    this.loadUser(userId);
  }

  loadUser(id: number): void {
    this.isLoading = true;
    this.http.get<any>(`${this.usersUrl}/${id}`).subscribe({
      next: (res) => {
        this.user = res.user || this.user;
        this.isLoading = false;
        this.cdr.detectChanges();
      },
      error: (err) => {
        console.error('Error cargando perfil', err);
        this.message = 'No se pudo cargar el perfil.';
        this.isLoading = false;
        this.cdr.detectChanges();
      }
    });
  }

  onFileChange(event: any): void {
    const file = event.target.files && event.target.files[0];
    if (!file) return;

    const userId = Number(localStorage.getItem('user_id'));
    const form = new FormData();
    form.append('photo', file);
    form.append('name', this.user.name || '');
    form.append('telefono', this.user.telefono || '');

    this.http.patch<any>(`${this.usersUrl}/${userId}`, form).subscribe({
      next: (res) => {
        this.user = res.user || this.user;
        this.message = res.message || 'Perfil actualizado';
        this.cdr.detectChanges();
      },
      error: (err) => {
        console.error('Error subiendo foto', err);
        this.message = 'No se pudo actualizar la foto.';
        this.cdr.detectChanges();
      }
    });
  }

  save(): void {
    const userId = Number(localStorage.getItem('user_id'));
    const form = new FormData();
    form.append('name', this.user.name || '');
    form.append('telefono', this.user.telefono || '');
    form.append('email', this.user.email || '');

    this.http.patch<any>(`${this.usersUrl}/${userId}`, form).subscribe({
      next: (res) => {
        this.user = res.user || this.user;
        this.message = res.message || 'Perfil actualizado';
        localStorage.setItem('user_name', this.user.name || '');
        this.cdr.detectChanges();
      },
      error: (err) => {
        console.error('Error actualizando perfil', err);
        this.message = 'No se pudo actualizar el perfil';
        this.cdr.detectChanges();
      }
    });
  }

  logout(): void {
    this.authService.logout().subscribe({
      next: () => this.router.navigate(['/login']),
      error: () => { this.authService.clearSession(); this.router.navigate(['/login']); }
    });
  }
}
