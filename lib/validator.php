<?php

/**
 * lib/validator.php
 * 入力検証。エラーは項目ごとにまとめ、最後に validate() で AppError として投げる。
 * $fields のキーは入力欄の name と同じにしておくと、画面でそのまま出せる。
 */

declare(strict_types=1);

require_once __DIR__ . '/support.php';

final class Validator
{
  /** @var array<string,string> 入力欄の name => エラーメッセージ */
  private array $errors = [];

  /** @param array<string,mixed> $data ふつうは $_POST */
  public function __construct(private array $data) {}

  public function required(string $key, string $label): self
  {
    $v = $this->data[$key] ?? null;
    if ($v === null || (is_string($v) && trim($v) === '') || $v === []) {
      $this->errors[$key] = "{$label}は必須です。";
    }
    return $this;
  }

  public function email(string $key, string $label = 'メールアドレス'): self
  {
    $v = trim((string) ($this->data[$key] ?? ''));
    if ($v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) {
      $this->errors[$key] = "{$label}の形式が正しくありません。";
    }
    return $this;
  }

  public function length(string $key, string $label, int $min, int $max): self
  {
    $v = (string) ($this->data[$key] ?? '');
    if ($v === '') {
      return $this;
    }
    $len = mb_strlen($v);
    if ($len < $min || $len > $max) {
      $this->errors[$key] = "{$label}は{$min}〜{$max}文字で入力してください。";
    }
    return $this;
  }

  public function password(string $key = 'password'): self
  {
    $v = (string) ($this->data[$key] ?? '');
    if ($v === '') {
      return $this;
    }
    // 長さを優先。そのうえで英字と数字（または記号）の混在を最低条件にする
    if (mb_strlen($v) < 10) {
      $this->errors[$key] = 'パスワードは10文字以上にしてください。';
    } elseif (!preg_match('/[A-Za-z]/', $v) || !preg_match('/[0-9\W_]/', $v)) {
      $this->errors[$key] = 'パスワードは英字と数字（または記号）を混ぜてください。';
    }
    return $this;
  }

  public function phone(string $key, string $label = '電話番号'): self
  {
    $v = (string) ($this->data[$key] ?? '');
    if ($v !== '' && self::normalizePhone($v) === null) {
      $this->errors[$key] = "{$label}は SMS を受信できる携帯番号を入力してください。";
    }
    return $this;
  }

  public function tel(string $key, string $label = '電話番号'): self
  {
    $v = (string) ($this->data[$key] ?? '');
    if ($v !== '' && self::normalizeTel($v) === null) {
      $this->errors[$key] = "{$label}の形式が正しくありません。";
    }
    return $this;
  }

  public function date(string $key, string $label): self
  {
    $v = (string) ($this->data[$key] ?? '');
    if ($v !== '' && !self::isDate($v)) {
      $this->errors[$key] = "{$label}は YYYY-MM-DD 形式で入力してください。";
    }
    return $this;
  }

  public function datetime(string $key, string $label): self
  {
    $v = (string) ($this->data[$key] ?? '');
    if ($v !== '' && strtotime($v) === false) {
      $this->errors[$key] = "{$label}の日時形式が正しくありません。";
    }
    return $this;
  }

  public function minAge(string $key, int $age, string $label = '生年月日'): self
  {
    $v = (string) ($this->data[$key] ?? '');
    if ($v === '' || !self::isDate($v)) {
      return $this;
    }
    $birth = new DateTimeImmutable($v);
    if ($birth > (new DateTimeImmutable('today'))->modify("-{$age} years")) {
      $this->errors[$key] = "{$label}：{$age}歳未満の方はご利用いただけません。";
    }
    if ($birth < new DateTimeImmutable('1900-01-01')) {
      $this->errors[$key] = "{$label}が正しくありません。";
    }
    return $this;
  }

  public function intRange(string $key, string $label, int $min, int $max): self
  {
    $v = $this->data[$key] ?? null;
    if ($v === null || $v === '') {
      return $this;
    }
    if (!is_numeric($v) || (int) $v < $min || (int) $v > $max) {
      $this->errors[$key] = "{$label}は{$min}〜{$max}の範囲で入力してください。";
    }
    return $this;
  }

  /** @param list<string> $allowed 許可する値の一覧 */
  public function in(string $key, string $label, array $allowed): self
  {
    $v = (string) ($this->data[$key] ?? '');
    if ($v !== '' && !in_array($v, $allowed, true)) {
      $this->errors[$key] = "{$label}の値が不正です。";
    }
    return $this;
  }

  public function accepted(string $key, string $label): self
  {
    $v = $this->data[$key] ?? null;
    if (!in_array($v, [true, 1, '1', 'true', 'on'], true)) {
      $this->errors[$key] = "{$label}への同意が必要です。";
    }
    return $this;
  }

  public function url(string $key, string $label): self
  {
    $v = trim((string) ($this->data[$key] ?? ''));
    if ($v !== '' && !filter_var($v, FILTER_VALIDATE_URL)) {
      $this->errors[$key] = "{$label}のURL形式が正しくありません。";
    }
    return $this;
  }

  /** 法人番号13桁。チェックディジットまで検証する */
  public function corporateNumber(string $key): self
  {
    $v = (string) preg_replace('/\D/', '', (string) ($this->data[$key] ?? ''));
    if ($v === '') {
      return $this;
    }
    if (strlen($v) !== 13) {
      $this->errors[$key] = '法人番号は13桁の数字です。';
      return $this;
    }

    $sum = 0;
    for ($i = 0; $i < 12; $i++) {
      $digit = (int) $v[12 - $i];                 // 下位から 1桁目..12桁目
      $sum  += $digit * (($i % 2 === 0) ? 1 : 2); // 下から奇数桁は1、偶数桁は2
    }
    if ((int) $v[0] !== (9 - $sum % 9)) {
      $this->errors[$key] = '法人番号のチェックディジットが一致しません。';
    }
    return $this;
  }

  /** 個別に見つけたエラーを足す */
  public function add(string $key, string $message): self
  {
    $this->errors[$key] = $message;
    return $this;
  }

  public function fails(): bool
  {
    return $this->errors !== [];
  }

  /** @return array<string,string> */
  public function errors(): array
  {
    return $this->errors;
  }

  /** エラーがあれば AppError を投げる */
  public function validate(): void
  {
    if ($this->fails()) {
      throw new AppError('入力内容を確認してください。', $this->errors);
    }
  }

  // -------------------------------------------------------------------
  // 正規化（保存する前に形をそろえる）
  // -------------------------------------------------------------------

  /** 日本の携帯番号を E.164（+81…）にそろえる。携帯以外や形式違いは null */
  public static function normalizePhone(string $input): ?string
  {
    $s = (string) preg_replace('/[^\d+]/', '', $input);
    if (str_starts_with($s, '+81')) {
      $s = '0' . substr($s, 3);
    } elseif (str_starts_with($s, '81') && strlen($s) >= 12) {
      $s = '0' . substr($s, 2);
    }
    // SMS を送るため携帯番号のみ許可する
    return preg_match('/^0[5789]0\d{8}$/', $s) ? '+81' . substr($s, 1) : null;
  }

  /** 固定電話も含む日本の電話番号を E.164 にそろえる（SMS 送信には使わない） */
  public static function normalizeTel(string $input): ?string
  {
    $s = (string) preg_replace('/[^\d+]/', '', $input);
    if (str_starts_with($s, '+81')) {
      $s = '0' . substr($s, 3);
    }
    return preg_match('/^0\d{9,10}$/', $s) ? '+81' . substr($s, 1) : null;
  }

  /** 「７３３００３６」「733 0036」などを「733-0036」にそろえる。7桁でなければ null */
  public static function normalizePostalCode(string $v): ?string
  {
    $digits = (string) preg_replace('/\D/u', '', mb_convert_kana($v, 'n'));
    return strlen($digits) === 7 ? substr($digits, 0, 3) . '-' . substr($digits, 3) : null;
  }

  public static function isDate(string $v): bool
  {
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $v);
    return $d !== false && $d->format('Y-m-d') === $v;
  }
}
