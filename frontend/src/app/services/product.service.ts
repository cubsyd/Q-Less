import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { environment } from '../../environments/environment';

@Injectable({
  providedIn: 'root'
})
export class ProductService {
  private API_URL = `${environment.apiBaseUrl}/productos`;
  private CATEGORIES_URL = `${environment.apiBaseUrl}/categorias`;

  constructor(private http: HttpClient) {}

  getProducts(): Observable<any> {
    return this.http.get<any>(this.API_URL);
  }

  getCategories(): Observable<any> {
    return this.http.get<any>(this.CATEGORIES_URL);
  }

  getProduct(id: number | string): Observable<any> {
    return this.http.get<any>(`${this.API_URL}/${id}`);
  }

  createProduct(productData: FormData): Observable<any> {
    return this.http.post(`${this.API_URL}`, productData, { responseType: 'text' }).pipe(
      map((response) => this.parseOptionalJson(response))
    );
  }

  updateProduct(id: number | string, productData: any): Observable<any> {
    if (productData instanceof FormData) {
      if (!productData.has('_method')) {
        productData.append('_method', 'PUT');
      }

      return this.http.post(`${this.API_URL}/${id}`, productData, { responseType: 'text' }).pipe(
        map((response) => this.parseOptionalJson(response))
      );
    }

    return this.http.put<any>(`${this.API_URL}/${id}`, productData);
  }

  deleteProduct(id: number | string): Observable<any> {
    return this.http.delete<any>(`${this.API_URL}/${id}`);
  }

  private parseOptionalJson(response: string): any {
    const trimmed = response.trim();

    if (!trimmed) {
      return {};
    }

    const jsonStart = trimmed.search(/[\[{]/);

    if (jsonStart === -1) {
      return { raw: response };
    }

    try {
      return JSON.parse(trimmed.slice(jsonStart));
    } catch {
      return { raw: response };
    }
  }
}
