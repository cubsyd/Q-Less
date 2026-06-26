import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';
import { AuthService } from './auth';

export interface RouletteReward {
  id: number;
  prize_type: string;
  label: string;
  discount_percent?: number | null;
  expires_at?: string | null;
  remaining_seconds?: number;
}

@Injectable({
  providedIn: 'root'
})
export class RouletteService {
  private readonly API_URL = `${environment.apiBaseUrl}/roulette`;

  constructor(
    private http: HttpClient,
    private authService: AuthService
  ) {}

  getActiveReward(): Observable<{ status: boolean; reward: RouletteReward | null }> {
    const userId = this.authService.getUserId();
    return this.http.get<{ status: boolean; reward: RouletteReward | null }>(`${this.API_URL}/active/${userId}`);
  }

  spin(): Observable<{ status: boolean; message: string; reward: RouletteReward }> {
    return this.http.post<{ status: boolean; message: string; reward: RouletteReward }>(`${this.API_URL}/spin`, {
      user_id: this.authService.getUserId(),
    });
  }
}
