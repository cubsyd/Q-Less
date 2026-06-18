import { Component } from '@angular/core';
import { RouterOutlet } from '@angular/router';
import { CommonModule } from '@angular/common';
import { ConnectionStatusService } from './services/connection-status.service';

@Component({
  selector: 'app-root',
  standalone: true,
  imports: [RouterOutlet, CommonModule],
  templateUrl: './app.html',
  styleUrls: ['./app.css']
})
export class App {
  title = 'Q-LESS - Sistema de Gestion de Papeleria';

  readonly connectionMessage$ = this.connectionStatus.message$;

  constructor(private connectionStatus: ConnectionStatusService) {}
}
