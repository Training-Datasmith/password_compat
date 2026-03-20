# Architecture: password_compat

## Purpose

A compatibility shim providing PHP 5.5's `password_hash()`, `password_verify()`, `password_needs_rehash()`, and `password_get_info()` functions for PHP 5.3 and 5.4. On PHP 5.5+, none of these definitions are loaded (the native functions take precedence).

## Directory Structure

```
lib/
  password.php   — Polyfill implementations of the four password_* functions
version-test.php — Runtime check to verify bcrypt support is functional
```

## Key Design Decisions

- **Guard-wrapped**: Each function is wrapped in `if (!function_exists(...))`, so including this file on PHP 5.5+ is a no-op
- **Timing-safe verification**: `password_verify()` uses a constant-time XOR loop instead of `==` to prevent timing attacks
- **Binary-safe helpers**: A private `Password_Compat\binary` namespace provides `_strlen()` and `_substr()` that use `mb_strlen`/`mb_substr` in `8bit` mode, preventing multibyte extension interference with raw bytes
- **Entropy sources**: Salt generation tries `mcrypt_create_iv`, then `openssl_random_pseudo_bytes`, then `/dev/urandom`, falling back to `mt_rand` XOR

## Dependency Flow

```
password_hash()
  └── Password_Compat\binary\_strlen(), _substr()  — byte-safe string ops

password_verify()
  └── crypt()  — PHP built-in
  └── Password_Compat\binary\_strlen()

Password_Compat\binary\check()  — verifies bcrypt is working on this platform
```

## Security Notes

- The `cost` parameter for bcrypt must be between 4 and 31
- User-supplied salts are deprecated in PHP 7.0+ and ignored here if they are too short
- `password_verify()` is constant-time against the hash length to prevent timing oracle attacks
