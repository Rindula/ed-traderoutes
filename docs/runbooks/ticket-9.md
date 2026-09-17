# Ticket 9: Kubernetes operations

`deploy/kubernetes/ticket-9.yaml` is the Kubernetes deployment successor to
`ticket-2.yaml`. Apply this file as the complete deployment manifest after
replacing the secret and SMB example values.

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
the cluster's configured default StorageClass. Do not change the access mode
or reuse the SMB backup class for application data.

Before applying, replace the SMB CSI `source`, `username`, `password`, and
`domain` examples. The SMB PV uses `smb.csi.k8s.io`, `ReadWriteMany`, and
`Retain`; it is only mounted by the PostgreSQL backup CronJob. Web, worker,
PostgreSQL, Redis, catalog, and cleanup pods have no SMB volume or mount.

## Scheduled operations

- `ed-traderoutes-catalog-weekly` runs Sundays at 03:00 UTC. Its
  `jobTemplate` is also the source for an ad-hoc run:
  `kubectl create job --from=cronjob/ed-traderoutes-catalog-weekly catalog-manual-$(date +%s) -n ed-traderoutes`.
- `ed-traderoutes-catalog-manual` is a suspended, applyable Job template. Set
  `spec.suspend: false` and give it a new name when applying a manual run.
- `ed-traderoutes-retention-cleanup` runs daily at 03:30 UTC and invokes
  `app:cleanup-market-data` with the ADR 0013 limits: normalized market history
  30 days and raw events 72 hours.
- `ed-traderoutes-postgres-backup` runs daily at 04:00 UTC and writes a custom
  `pg_dump` archive to `/backup` on the SMB share.

All operation Jobs use `concurrencyPolicy: Forbid`, a deadline, bounded history,
and a retrying `backoffLimit`. A non-zero container exit leaves the Job failed
after retries. The included `PrometheusRule` alerts on failed Jobs and on a
stale PostgreSQL backup; the existing labels and annotations remain available
for other monitoring integrations.

An SMB outage therefore retries and becomes visible as a failed backup Job;
it cannot make the web or worker deployment unready. The normal web/worker
HTTP/exec probes remain independent of the backup volume.

## Verification

```sh
kubectl apply --dry-run=server -f deploy/kubernetes/ticket-9.yaml
kubectl get cronjobs,jobs,pvc -n ed-traderoutes
kubectl describe job -l workload=postgres-backup -n ed-traderoutes
```

For a controlled backup test, temporarily make the SMB source unreachable and
verify retries, a failed Job, and the monitoring alert. Restore the source
before the next scheduled run.
