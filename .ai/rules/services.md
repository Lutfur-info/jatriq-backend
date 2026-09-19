---
paths:
  - app/Services/OtpService.php
  - app/Services/VerificationService.php
---

# Services

## Issued OTP codes are hashed; the plain mirror is a dev-only seam
`send()` stores `Hash::make($code)` under `otp:msisdn:{msisdn}`, so an issued code can never be recovered from that record. While `otp.expose_codes` is on (default: any non-production env, hard-blocked in production) the plain code is mirrored to `otp:msisdn:{msisdn}:plain` for the same TTL, and `OtpService::issuedCode()` / `POST /api/msisdn/code` read that mirror.

`verify()` must keep comparing against the hash only — never the mirror. Any new key holding a code must be dropped in `forget()` alongside the others, or a consumed code stays readable.

## The verification badge is derived, never assigned
`users.verification_status` is computed by `refreshBadge()` from the documents `DocumentType::requiredFor($user->role)` demands - never written from a request or a controller. Every path that changes a document (upload, replacement, approval, rejection) must end by calling it, or the badge drifts from the documents.

Precedence in `deriveStatus()`: no requirements (Admin) -> Unverified; any required document Rejected -> Rejected; any required document missing -> Unverified; all Approved -> Verified; otherwise Pending. A rejection deliberately outranks the documents still queued behind it, because that is what the applicant must act on.

This is a fourth flag, distinct from `msisdn_verified_at` (owns the number), `is_active` (may use the account) and `email_verified_at` (unused). Do not conflate them.

Re-uploading a document resets it to Pending and clears the earlier decision (`UserDocumentEloquentRepository::put()`), so an approval can never carry over to a file that replaced it.
