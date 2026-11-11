# TASK-00064 integrated security evidence

This is Work's assertion-level reconciliation, not independent acceptance. Source: the routes/OpenAPI and
TICKET-00014–00021 at the TASK's candidate. Exact execution receipts, counts, runtime and limitations belong in
[the TASK](../../planning/tasks/00064-TASK.md) and its canonical Engineer handoff. Tests below live under
`tests/Integration/Postgres/` unless qualified. Method names identify executable assertions, not inferred
coverage from filenames or aggregate suite counts. No production behavior or supplier is replaced here.

## Common negative dimensions

`ApiActionSecurityTest` challenges **all 31 protected operations** with 14 authentication profiles:
anonymous, cookie-only, invalid signature, revoked session, stale authentication version, pending/disabled/deleted
user, idle/absolute expiry, missing authoritative role, wrong subject/session owner, missing session and prohibited
JWT role/permission snapshots. Each challenge requires exact safe 401, no cookie/CORS grant, zero query dispatch,
zero current-principal Action construction and unchanged hashes of complete public-schema tables. Its 29 grant
sets independently vary the 25 managed permissions, ordinary ROLE_USER, a permissionless misleading
ROLE_SUPER_ADMIN, and invitation/elevation/read/manage combinations. The **803** insufficient-permission
requests require exact safe 403 before dispatch/write; the **434** authentication requests do not substitute
for the positive assertions below. Permissionless `/me` and password change correctly have no permission 403.

The route inventory has **39 operations**: 31 protected and eight public. The two documentation transport
operations are intentionally absent from generated OpenAPI paths; all other 37 reconcile. Parameters below are
package-generated IDs, paths relative to `/api/v1`. READ/WRITE permission names are exact, not role labels.

## Protected-operation positive matrix

| Method/path | Admission beyond valid current Bearer | Positive assertion and effect |
|---|---|---|
| GET `/documentation/bootstrap` | READ_SWAGGER and eligible exact environment | `SwaggerApiTest::testEnvironmentPolicy`: 200 exact viewer/document-path projection |
| GET `/documentation/openapi` | READ_SWAGGER and eligible exact environment | Same method: 200 exact private document; ineligible environment 404 without contents |
| GET `/me` | No dashboard permission | `ApiActionSecurityTest::testSafeCurrentPrincipal`: safe current identity/roles/permissions, not token snapshots |
| POST `/auth/password/change` | Principal ownership and current proof | `PasswordChangeApiTest::testSuccessAndAllSessionInvalidation`: 200 changed, actual hash/version replacement and all sessions unusable |
| POST `/invitations` | INVITE_USERS; elevated mode also ASSIGN_SUPER_ADMIN and confirmation | `InvitationApiTest::testIssuance`: 201 pending canonical identity, seven-day grant and exact ordinary/elevated role; independent assignment/audit result fields |
| GET `/invitations/{user_id}` | READ_USERS | `InvitationApiTest::testIssuance`: 200 delivery matches issuance's safe projection; `InvitationRecoveryApiTest::testStatusReadPermission`: read/manage independence |
| POST `/invitations/{user_id}/retry` | MANAGE_USER_INVITATIONS | `InvitationRecoveryApiTest::testRetry`: 200 null data, same grant/delivery/digest/expiry, revision advances; `testManageOnlyRetryWithoutDisclosure` |
| POST `/invitations/{user_id}/resend` | MANAGE_USER_INVITATIONS | `testReplacement` resend dataset: 200 null data, fresh successor, predecessor revoked/ciphertext destroyed, assignments retained |
| PATCH `/invitations/{user_id}/email` | MANAGE_USER_INVITATIONS | `testReplacement` email dataset: 200 null data, canonical corrected address and successor atomically committed; `testManageOnlyWriteWithoutDisclosure` |
| POST `/workspaces/{workspace_id}/projects` | REGISTER_PROJECTS plus Workspace admission | `ProjectRegistrationTest::testCreationAndFreshQueryRecovery`: 201 ID; real detail/list recovers committed project, duplicate 409 without original response replay |
| POST `/workspaces/{workspace_id}/projects/conflict-preview` | READ_PROJECTS plus Workspace admission | `testSourcesPreviewAndPagination`: 200 exact slug/source conflicts with unchanged counts |
| GET `/workspaces/{workspace_id}/projects` | READ_PROJECTS plus Workspace admission | Same method: 200 disjoint ordered pages, unchanged counts |
| GET `/workspaces/{workspace_id}/projects/{project_id}` | READ_PROJECTS plus Workspace admission | `testCreationAndFreshQueryRecovery`: 200 exact Workspace/creator/display name; absent Project 404 in `testSourcesPreviewAndPagination` |
| POST `/workspaces/{workspace_id}/references` | GENERATE_REFERENCE_IDS plus Workspace admission | `testConcurrentReferencesAndRollover`: 200 package-valid UUID, reserved=false, no Project creation/reservation |
| POST `/roles` | CREATE_ROLES | `AuthorityCatalogApiTest::testNameOnlyCreation`: 201 generated ID, stored custom Role/revision and exact subject audit |
| PATCH `/roles/{role_id}` | UPDATE_ROLES plus expected revision | `testCustomRoleJourney`: 200 no-op keeps revision/audit count; real rename advances revision and audit |
| DELETE `/roles/{role_id}` | DELETE_ROLES plus expected revision/confirmation | Same method: 200 removes custom Role and exact removal audit; unconfirmed request 400 |
| GET `/roles` | READ_ROLES | `testSafeViewsAndPagination`: 200 ordered disjoint pages and exact four-field safe Role projection |
| GET `/roles/{role_id}` | READ_ROLES | Same method: detail equals list item; `testNotFoundAndNoMutations`: absent 404 |
| GET `/permissions` | READ_PERMISSIONS | Same method: 200 safe ID/name/tier/managed fields, pagination, no authority assignment |
| GET `/permissions/{permission_id}` | READ_PERMISSIONS | Same method: exact detail/list equality; absent 404 in `testNotFoundAndNoMutations` |
| GET `/me/sessions` | READ_OWN_SESSIONS plus package ownership | `OwnedSessionsApiTest::testOwnedFilteringAndPagination`: 200 only owned active sessions, one current marker, exact coarse fields |
| DELETE `/me/sessions/{session_id}` | DELETE_OWN_SESSIONS plus package ownership; not current | `OwnedSessionRevocationTest::testCommitAndImmediateTokenRejection` / `testImmediateRefreshRejection`: 200, actual revocation and immediate access/refresh refusal |
| GET `/users/{user_id}/sessions` | READ_SESSIONS plus administrative actor/target callback | `testAdministrativeHttpAndImmediateAuthority` / `testAdministrativeReadOnlyAndAnonymous`: 200 only target's safe sessions, independent of delete/self-service permission |
| DELETE `/users/{user_id}/sessions/{session_id}` | DELETE_SESSIONS plus administrative actor/target and safe reason | `testAdministrativeHttpAndImmediateAuthority`: 200 after READ removed, only target revoked, immediate access/refresh refusal, one required audit |
| GET `/users` | READ_USERS | `UserDirectoryApiTest::testSafeDirectory`: exact safe ordered page/roles and zero read audit |
| GET `/users/{user_id}` | READ_USERS | `testSafeDetail`: 200 exact safe fields across pending/active/disabled/deleted targets, canonical email/roles, zero read audit |
| DELETE `/users/{user_id}` | DELETE_USERS; current-state confirmation; no self-delete | `UserLifecycleApiTest::testDeleteRestoreRetainsIdentityWithoutSessionRevival`: 200 identity retained, deleted state, sessions retired |
| POST `/users/{user_id}/restore` | RESTORE_USERS; current-state confirmation | Same method: 200 restores identity without old sessions; `testPendingRestoration` retains pending grant rules |
| POST `/users/{user_id}/disable` | DISABLE_USERS plus lifecycle/last-admin precheck | `testDisableEnableAndImmediateAuthorityEffects`: 200 disabled, auth version advances, all target sessions retired and prior access 401 |
| POST `/users/{user_id}/enable` | ENABLE_USERS plus lifecycle admission | Same method: 200 active; no old session or access authority revived |

All rows also receive the common 401/stale/no-write and applicable independent-permission 403 matrix. Positive
cases use the actual owning services/package/DB, not an accepting fake Action. The matrix does not assert
that every irrelevant permission/target/role Cartesian combination must independently succeed.

### Target denials and operation-specific admission

- Self-service sessions: `OwnedSessionsApiTest::testStaleAndCrossOwner` and
  `OwnedSessionRevocationTest::testNonEnumeratingFailuresAndCurrentInstruction` prove stale/cross-owner
  refusal. Foreign, unknown, revoked and either-expired target return the same exact 409 State conflict;
  current target alone has the approved use_logout instruction, stays live and emits no revoke event.
- Administrative sessions: `testAdministrativeTargetBoundaries` compares missing session, wrong owner,
  unknown target and expired session (409), unknown/self read (403), self revoke (403), and empty active list.
  `testPackageCallbackDenial` retains package target refusal after HTTP permission admission and proves no write.
  This is an installation-wide administrative operation, not a cross-user prohibition.
- Directory: `testSafeDetail` expressly permits all lifecycle states under READ_USERS; missing/disappearing
  target and role faults remain safe in `testDetailTargetDisappears`, `testDetailMissingRole` and
  `testRoleResolutionFailure`. `testTokenClaimsCannotGrantDirectoryAccess`, `testRoleLabelCannotGrantAccess`
  and `testDetailRoleNameSpoof` deny manufactured authority. Authorized reading is not identity enumeration
  forbidden to an authorized directory operator.
- Invitations: `InvitationApiTest::testAuthority`, `testOrdinaryPermissionCombinations`,
  `testElevatedPermissionCombinations`, `testConfirmation`, `testMissingStatus` and
  `InvitationRecoveryApiTest::testAuthority`, `testActiveTargetWithoutInvitation`, `testConsumedInvitation`,
  `testMissingWork`, `testTerminalDelivery`, `testEmailClaim` exercise exact admission, generic unavailable
  state, no mutation, and no provider invocation where required. Managing alone returns null, never a read.
- Workspace/Project: `testAuthenticationAndWorkspaceRefusals` and `testWorkspaceMembershipAndBootstrapAbsence`
  keep unknown/unavailable scope at 403 without implicit bootstrap or changed counts;
  `testIndependentAdmission` varies create versus read independently. Source/fork identities are not conflated.
- Catalog: `testMutationPermissions`, `testMutationSecurity`, `testStaleRoleIntent`, `testRoleAuditRollback`
  preserve protected/managed/assigned/reference/revision refusals with no partial writes or success audit.
- Lifecycle: `testRejectedTargets`, `testMissingTarget`, `testInvalidEnableState`,
  `testDeleteRestoreEligibilityAndStrictInput`, `testIneligibleDeletionTarget`, `testDeletionRouteAndQueryAdmission`
  preserve safe target/state/input outcomes. Accepted last-admin precheck and delete/restore current-state
  confirmation amendments remain in force, not replaced with cross-target serialization.

## Public routes and request profiles

Public GET `/auth/csrf` and `/validations/{form_name}` expose only safe proof/schema. Public POST `/auth/login`,
`/auth/refresh`, `/auth/logout`, `/auth/activate`, `/auth/password-reset`, `/auth/password-reset/complete`
retain distinct accepted profiles; Bearer password change is not ambient authentication.

The **84** cross-route challenges in `ApiActionSecurityTest` exercise 12 independent violations on seven
credential mutations: Origin absent/null/foreign/wrong port, cross-site Fetch Metadata, navigation/destination,
forwarded authority, content type, query source, array body and HTTP. Exact 400/403 precedes dispatch and complete
state changes; generated credential markers stay out of response/header/diagnostics. Default HTTPS port
normalization is intentional. `AuthRequestControlsTest` supplies CSRF and method/source profile detail;
`ResetRequestApiTest::testStrictPreDispatchControls` also requires zero waits/grants/audit/admission effects.
Owning login/activation/reset/change/session suites test successful protected requests and their exact cookie,
origin, schema, malformed-body and cookie-versus-Bearer contracts. No client visibility assertion grants access.

## Limiter reconciliation

The limits below are current consumer defaults, not new policy. Reduced configured policies in contention
workers challenge the same atomic adapter at its last remaining slot. All time boundaries use explicit clocks
or owned persisted expiry manipulation; all competitions use separate PostgreSQL connections and bounded lock
barriers, never scheduling sleeps.

| Policy / dimensions | Below/at/over, expiry and isolation assertions | Controlled contention / failure |
|---|---|---|
| Login canonical email: 5 / rolling 900s | `AuthAdmissionTest::testRollingEmailAndRedactedKeys`: all five admitted, sixth refused, case normalization, other email independent, fixed-window crossing does not reset, exact microsecond expiry | `testConcurrency` email: four preconsumed, three processes reach advisory lock, exactly one admission |
| Each LOGIN/REFRESH/LOGOUT/RESET_COMPLETION/PASSWORD_CHANGE peer 20 and endpoint 300 / rolling 900s | `testConfiguredEndpointBoundaries` all five purposes: twentieth and 300th admitted, next denied, varying peers cannot bypass endpoint, just-before/exact expiry, bounded opaque keys | `testConcurrency` each AuthOperation's peer and endpoint dimension separately; `testPurposeIsolation`, `testCapacityAndEndpointIsolation`, `testNestingAndOutage`, `testBusinessRollbackCannotUndoAdmission` |
| Reset request email 3, peer 20, endpoint 300 / rolling 900s, one atomic consumption | `testResetEmailBoundaryAndIsolation`, `testResetPeerAndEndpointBoundaries`; `ResetRequestApiTest::testAtomicLimitsExpiryAndRedaction`: known/unknown same 429, exact delay, unchanged complete admission rows on denied request, no email/IP/key retention | `testConcurrency` reset_email/reset_peer/reset_endpoint: three separate contenders, one winner; request business rollback leaves independent admission committed |
| Activation peer 10, endpoint 300 / fixed 900s; retained capacity 10,000 | `ActivationApiTest::testRateBoundary` tenth attempt then 429 and opaque keys; `testEndpointBudgetAndIsolation` admits 300 distinct peers, rejects 301st before dispatch with unchanged rows, denies at 899s and admits exactly at 900s; `testAdmissionCapacityAndExpiry` capacity refusal/expired-row reclamation | `testControlledAtomicCompetition` admission dataset: separate ready-barrier workers with a shared fixed admission clock, exact peer/endpoint remaining-slot winner; `testAdmissionStorageFailure` fails closed |
| Invitation ISSUE 10, STATUS 60, RETRY 5, RESEND 3, CORRECT 3 / fixed 60s | `InvitationRateLimiterTest::testConfiguredBoundariesAndExpiry` all five: exact production limit, over denied with unchanged rows, changing target cannot bypass actor, independent actor/target, 59s refusal / exact 60s admission. `testWindowsAndIsolation`, `testTargetCapacity`, `testCapacity` cover operation isolation and bounded atomic scopes | `testConcurrentAdmission` now all five including STATUS: three real blocked processes, exactly one remaining-slot admission; no accidental business nesting |
| Reference generation: actor 10 / fixed 60s | `ProjectRegistrationTest::testConcurrentReferencesAndRollover`: ten valid unreserved IDs, two 429, 59s denial / 60s admission; `testReferenceBucketCapacity` retained capacity and existing actor budget | Twelve separate workers use the existing controlled `compete` barrier; business counts unchanged |
| Provider delivery retry/backoff/claims (not another HTTP rate policy) | `DirectCredentialDeliveryTest::testPrematureRetryCannotInvokeProvider`, `testDeliveredDuplicateCannotInvokeProvider`, `testReplacedGenerationCannotInvokeProvider`, `testRecoveryRetriesUntilPackageExpiry`, `testFractionalDeliveryDeadlineCannotCommitLateOutcome` | `testCompetingClaimCannotInvokeProvider`, `testRecoveryWorkersFenceLiveAndStaleOutcomes`, provider-success/outcome-rollback and crash/restart cases. Approved direct package claim/backoff/expiry owns delivery; no invented SMTP quota |

## Audit reconciliation

Audits record successful facts, not a blanket log of every denied request. Empty safe context is intentional;
there is no invented result column. Approved action names and safe status express actual result, with separate
post-commit uncertainty where applicable. `AuditDeliveryPersistenceTest::testAuditRoundTripsTypedSubjectsAndRollsBackWithOriginatingWork`
now brackets actual `recorded_at` using the database's own before/after clock, proves same-transaction time and
rolls back originating work plus audit. Package fake business time is not falsely substituted for DB recording time.

| Owner / successful fact | Exact attribution/context assertions | Failure / no false success |
|---|---|---|
| Bootstrap | `BootstrapTest::testSuccessAndRecoverableDelivery`: console operator versus bootstrap actor prefixes, target ID, exact actions, empty context | `testIndependentFailures`, `testPostcommitFailure`, `testLostCompletionAcknowledgement`: independent commits/partial uncertainty, not fictional cross-step rollback |
| Ordinary/elevated invitation | `InvitationApiTest::testIssuance`: target-scoped user.invited and user.invitation_role_confirmed, authenticated actor, exact role and safe delivery projection | `testPartialFailure`, `testDelivery`, permission/confirmation cases; independent invitation/assignment/audit steps remain truthful |
| Retry/resend/correct | `InvitationRecoveryApiTest::testRetry`, `testReplacement`: exact retry count / successor action, actual actor and target; predecessor invalidation and safe status | `testRollback` checks grant/user/required-audit write failures preserve prior state and audit count; authority/terminal cases do not claim mutation |
| Delivery worker | `DirectCredentialDeliveryTest::testDirectHandlersCommitClaimsBeforeProviderAndRecordOutcomes`, `testUnexpectedProviderExceptionRemainsRetryableWithoutErrorDisclosure`: typed safe delivery results; no exception/grant/digest/ciphertext in safe status or audit | Claim/outcome rollback, stale claim and restart cases preserve exact generation/idempotency and do not overwrite authoritative outcomes |
| Reset/password | `ResetCompletionApiTest::testSuccessRevokesEverySessionAndAdvancesAuthority`: committed password_reset_completed visible after commit, all sessions retired; `PasswordChangeApiTest::testSuccessAndAllSessionInvalidation` | `testAtomicRollback` and reset per-write rollback cases: no changed hash/session/grant or success event after failure; unrelated identity untouched |
| Self/admin session security | `OwnedSessionRevocationTest::testPackageAdministrativeAudit`: exact actor, target owner (not session/actor confusion), action, nonempty recording time, bounded reason/session ID, no digest | `testAdministrativeAuditFailureRollsBack`, `testStorageFailureRedactionAndRollback`, generic target failures: no revocation/success event on failure. Self revoke does not invent administrative audit |
| User lifecycle | `UserLifecycleApiTest::testCompetingLifecycleCommands` and `testDeleteRestoreRetainsIdentityWithoutSessionRevival`: exact actions/actor/target/empty context | `testRequiredAuditFailureRollsBack`, `testDeletionAuditRollback`; same-target CAS competition produces valid state/single audit; cross-admin race amendment retained |
| Role CRUD | `AuthorityCatalogApiTest::testCustomRoleJourney`: exact create/rename/remove sequence, actor, Role subject and empty context | `testRoleAuditRollback`, `testStaleRoleIntent`; no-op rename does not append a false change |
| Workspace/Project | `ProjectRegistrationTest::testCreationAndFreshQueryRecovery`, `testExplicitBootstrapCurrentStateAndAuditRollback` | `testRequiredAuditRollback`, lost commit acknowledgement and post-commit dispatch tests distinguish rollback from committed uncertainty |

Read-only directory/owned-session/catalog/preview operations do not acquire fabricated success audit obligations.
Admission-time authority suffices for ordinary operations: `ProjectRegistrationTest::testAdmissionTimeRevocationPolicy`
explicitly preserves admitted completion and next-request refusal. Races validate business CAS/claims/locks, not
unapproved universal permission-revocation-through-commit serialization.

## Seeded campaign and observable boundaries

`Tests\Support\SecretObservability` is called by the actual request helpers in 15 owning suites: activation,
login, refresh/logout, reset request/completion, password change, invitation issuance/status and recovery,
current principal, owned-session reads/revocation and administrative sessions, user directory/lifecycle,
Role/Permission catalogs and Role mutations, and Workspace/Project registration/reads/references.
It scans **every helper response**, including denials/provider/storage failures, across body (raw and decoded),
all headers/correlation, serialized captured diagnostic records (including formatted output) and complete audit.
Cases installing their own expected-failure handler retain their existing explicit handler assertions. A deliberately
renamed/unavailable persistence table is not scanned as if readable: known fixture digest/token material is still
checked, and the original owning test proves safe denial rather than a false empty successful read.
Markers are actual process-private passwords/current proofs/grants/Bearer/refresh/CSRF material, actual stored
password hashes/current and used refresh digests/grant digests/retry ciphertext, actual ephemeral configured
CSRF/activation/auth/delivery keys and signing secrets (including local injected signer material where applicable).
Issuance captures the actual provider invocation grant; reset-request's generator capture checks the actual new
raw credential. Nothing prints a marker on assertion failure.

Successful access_token JSON and Secure HttpOnly refresh Set-Cookie are intentional credential delivery:
returned access is independently absent from headers/log/audit; returned refresh is absent from body/log/audit.
Input secrets and protected storage/key material remain forbidden in **all** four surfaces. Protected
hash/digest/ciphertext persistence is an expected state, not an operational log or a false scan failure.
`SafeErrorBoundaryTest` separately routes 14 independently generated synthetic families through an actual
QueryBus exception and scans four safe error surfaces (56 family/surface absence assertions), including
provider/SQL/stack/link markers. These synthetic labels are not claimed as actual configured supplier keys.
`DirectCredentialDeliveryTest` supplies real activation/reset grant/digest/ciphertext provider-failure evidence.

Native browser campaign complements, not duplicates, the API suites: real canonical HTTPS activation success
and consumed replay, reset success and consumed replay, old-password login failure and new-password login
success; six visually inspected captures, non-input DOM, storage, visible URL/history, referrer, console,
request URL/referrer and five runtime log streams. Browser-owned userTypedURL remains accepted under
S64-HISTORY-01. The earlier two-intake probes additionally inspect all pre-capture request headers/bodies.
The successful-login capture is the safe authority-checking transition, not a claimed dashboard journey.

A currently edited masked password input is authorized transient invocation material, not an error projection:
its value remains while retrying login. The native probe never persists a DOM dump and excludes **only** that
input's value attribute from the normal-output scan; screenshots show bullets, not the password. Successful
activation/reset remove the forms. Navigating/closing the isolated browser disposes input memory. This is not
an exception for hidden fields, storage, diagnostics, capture metadata or tokens. Credential POST bodies and
same-origin Bearer/cookie transport are also deliberate invocation, never called secret-free HTTP traffic.

The native probe retains actual browser seed values, provider-time digest/ciphertext, final password/session
hashes and configured runtime key values only in process memory. It checks exact bytes in OpenAPI/validation
exports, live and production-check assets, complete build reports (including coverage/snapshots/logs/dumps),
and campaign artifacts; then checks live audit and five app/FPM/tooling/shared-proxy log streams. Counts and
family names only survive. Compressed image-byte absence is not OCR: authored six screenshots are visually
inspected, while the canonical story captures use synthetic production-story fixtures and their owning
artifact/network checks. No arbitrary browser-generated credential is injected into story fixture inputs.

No analytics/metrics exporter or browser trace/video recording is enabled for these journeys. Their absence
is applicability, not a fictional scan of an external sink. Provider production credentials, production mail,
external scanning/penetration certification and deployment remain outside this TASK. The real local catcher
is credential-bearing by design: private network, no host ports; browser fixtures intercept delivery in-process,
never send an external email, and final catcher count is checked separately. Native probe closes contexts and
clears task-owned development fixture state; the canonical backend gate cleans its separate guarded test schema.
Runtime remains for independent roles, not abandoned. No raw secret/dump/catcher content is retained as evidence.

## Chronology and qualification

No production defect has been confirmed or repaired. New checks expose no forbidden normal-output marker.
Authoring corrections are not regressions: exact labels include required decoration; full-document navigation
is needed to retest an intentionally retired possession page; reset delivery is explicitly asynchronous and
needs its supported direct worker command; active masked input is the invocation boundary described above.
Initial read-only PHPCBF could not write source and changed nothing; formatting is corrected explicitly.
An initial endpoint-race fixture bound its clock after eager route construction and therefore seeded an already
expired window; the worker now binds the fixed clock before constructing routes. Shared scanner authoring also
needed to distinguish HTML from JSON and the deliberately renamed authoritative table. These are test-fixture
corrections, not changed product denial or redaction behavior.
These observations do not consume an acceptance repair or waive a blocking finding. Independent Review and
QA still challenge the committed evidence and its stated applicability boundaries.
