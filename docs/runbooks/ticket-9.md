# Ticket 9: Kubernetes operations

`deploy/kubernetes/ticket-9.yaml` is the Kubernetes deployment successor to
`ticket-2.yaml`. Apply this file as the complete deployment manifest after
replacing the application secret values.

The manifest contains the generated application, PostgreSQL, and Redis
secrets. Replace `OIDC_CLIENT_ID` and `OIDC_CLIENT_SECRET` with the values from
Authentik before syncing. For production, prefer SealedSecrets or an external
secret manager so credentials are not stored in Git.

The manifest targets k3s' Traefik ingress and cert-manager. It publishes the
application at `https://trade-routes.rindula.de`, requests the certificate from
the `letsencrypt-prod` ClusterIssuer, and uses `trade-routes-tls` as the
generated Secret. Create a DNS A/AAAA record for `trade-routes.rindula.de`
pointing at the k3s ingress address before applying it, and register
`https://trade-routes.rindula.de/auth/callback` in Authentik.

## Container image

`.github/workflows/container.yml` builds the image on pull requests and pushes
to `main`, publishes it to `ghcr.io/rindula/ed-traderoutes`, and adds `latest`,
branch, tag, and immutable commit-SHA tags. Pull requests are build-only; the
workflow does not publish untrusted fork code. The Kubernetes manifest uses the
`latest` tag by default, while production deployments can pin a `sha-*` tag.

## Storage

Both StatefulSet volume claim templates omit `storageClassName`, so k3s uses
the cluster's configured default StorageClass.

## Scheduled operations

- `ed-traderoutes-catalog-weekly` runs Sundays at 03:00 UTC. Its
  `jobTemplate` is also the source for an ad-hoc run:
  `kubectl create job --from=cronjob/ed-traderoutes-catalog-weekly catalog-manual-$(date +%s) -n ed-traderoutes`.
- `ed-traderoutes-catalog-manual` is a suspended, applyable Job template. Set
  `spec.suspend: false` and give it a new name when applying a manual run.
- `ed-traderoutes-retention-cleanup` runs daily at 03:30 UTC and invokes
  `app:cleanup-market-data` with the ADR 0013 limits: normalized market history
  30 days and raw events 72 hours.

All operation Jobs use `concurrencyPolicy: Forbid`, a deadline, bounded history,
and a retrying `backoffLimit`. A non-zero container exit leaves the Job failed
after retries. The included `PrometheusRule` alerts on failed Jobs; the
existing labels and annotations remain available for other monitoring
integrations. The normal web/worker HTTP/exec probes remain independent of
scheduled operations.

## Verification

```sh
kubectl apply --dry-run=server -f deploy/kubernetes/ticket-9.yaml
kubectl get cronjobs,jobs,pvc -n ed-traderoutes
kubectl describe job -l workload=retention -n ed-traderoutes
```
