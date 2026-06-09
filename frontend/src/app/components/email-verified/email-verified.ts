import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute } from '@angular/router';
import { RouterModule } from '@angular/router';

@Component({
  selector: 'app-email-verified',
  standalone: true,
  imports: [CommonModule, RouterModule],
  templateUrl: './email-verified.html',
  styleUrls: ['./email-verified.css']
})
export class EmailVerifiedComponent {
  status = 'success';

  constructor(private route: ActivatedRoute) {
    this.status = this.route.snapshot.queryParamMap.get('status') || 'success';
  }

  get isInvalid(): boolean {
    return this.status === 'invalid';
  }
}
