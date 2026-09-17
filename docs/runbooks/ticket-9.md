# Ticket 9: Kubernetes operations

`deploy/kubernetes/ticket-9.yaml` is the Kubernetes deployment successor to
`ticket-2.yaml`. It intentionally leaves `ticket-2.yaml` unchanged. Apply this
file as the complete deployment manifest after replacing the example values.

## Storage

Set `storageClassName` in both StatefulSet volume claim templates to the
cluster's provisioner. The value is deliberately explicit (`ed-traderoutes-storage`)
so PostgreSQL and Redis do not silently depend on a cluster default. Do not
change the access mode or reuse the SMB backup class for application data.

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
after retries. The `monitoring: required` label and
`monitoring.ed-traderoutes.io/failure-signal: job-failed` annotation are the
alert signal: monitoring should alert on failed Jobs and on a missing successful
catalog/cleanup/backup completion within the expected window.

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
