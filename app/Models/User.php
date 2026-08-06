<?php

namespace App\Models;

/**
 * Encja Użytkownika (Data Object).
 *
 * Klasa reprezentuje pojedynczy rekord użytkownika pobrany z bazy danych.
 * Służy jako niemutowalny (w ramach sesji) obiekt transferu danych (DTO), 
 * przechowujący kluczowe parametry profilu, uprawnienia oraz statusy 
 * uwierzytelnienia (lokalnego lub Google).
 *
 * @version 1.1.0
 * @author Tobiasz Szerszeń
 * @package App\Models
 */
class User
{
  /** @var int Unikalny identyfikator użytkownika w systemie */
  private int $id;

  /** @var string|null Zewnętrzny identyfikator konta Google (jeśli połączone) */
  private ?string $googleId;

  /** @var string Dostawca tożsamości: 'local' lub 'google' */
  private string $authProvider;

  /** @var string Imię użytkownika */
  private string $firstName;

  /** @var string|null Nazwisko użytkownika */
  private ?string $lastName;

  /** @var string Podstawowy adres e-mail (używany do logowania) */
  private string $email;

  /** @var bool Flaga określająca, czy adres e-mail przeszedł weryfikację */
  private bool $isVerified;

  /** @var bool Status konta (np. false dla kont zablokowanych) */
  private bool $isActive;

  /** @var string Rola w systemie (np. 'user', 'admin') */
  private string $role;

  /** @var string|null Ścieżka do zdjęcia profilowego (awatara) */
  private ?string $avatarPath = null;

  /**
   * Tworzy nowy obiekt użytkownika na podstawie surowych danych z bazy.
   *
   * @param array $data Asocjacyjna tablica rekordów SQL.
   */
  public function __construct(array $data)
  {
    $this->id = (int) $data['id'];
    $this->googleId = $data['google_id'] ?? null;
    $this->authProvider = $data['auth_provider'] ?? 'local';
    $this->firstName = $data['first_name'];
    $this->lastName = $data['last_name'];
    $this->email = $data['email'];
    $this->isVerified = (bool) $data['is_verified'];
    $this->isActive = (bool) ($data['is_active'] ?? true);
    $this->role = $data['role'];
    $this->avatarPath = $data['avatar_path'] ?? null;
  }

  // --- GETTERY ---

  /** @return int */
  public function getId(): int
  {
    return $this->id;
  }

  /** @return string|null */
  public function getGoogleId(): ?string
  {
    return $this->googleId;
  }

  /** @return string */
  public function getAuthProvider(): string
  {
    return $this->authProvider;
  }

  /** @return string */
  public function getFirstName(): string
  {
    return $this->firstName;
  }

  /** @return string|null */
  public function getLastName(): string
  {
    return $this->lastName;
  }

  /**
   * Zwraca sformatowane imię i nazwisko użytkownika.
   * 
   * @return string
   */
  public function getFullName(): string
  {
    return "{$this->firstName} {$this->lastName}";
  }

  /** @return string */
  public function getEmail(): string
  {
    return $this->email;
  }

  /**
   * Sprawdza, czy użytkownik ma rolę administratora.
   *
   * @return bool
   */
  public function isAdmin(): bool
  {
    return $this->role === 'admin';
  }

  /**
   * Sprawdza, czy użytkownik ma rolę redaktora treści.
   *
   * @return bool
   */
  public function isRedaktor(): bool
  {
    return $this->role === 'redaktor';
  }

  /**
   * Czy użytkownik może zarządzać treścią merytoryczną (pytania, arkusze).
   *
   * @return bool
   */
  public function canManageContent(): bool
  {
    return $this->isAdmin() || $this->isRedaktor();
  }

  /** @return string */
  public function getRole(): string
  {
    return $this->role;
  }

  /** @return bool */
  public function isVerified(): bool
  {
    return $this->isVerified;
  }

  /** @return bool */
  public function isActive(): bool
  {
    return $this->isActive;
  }

  /** @return string|null */
  public function getAvatarPath(): ?string
  {
    return $this->avatarPath;
  }
}
