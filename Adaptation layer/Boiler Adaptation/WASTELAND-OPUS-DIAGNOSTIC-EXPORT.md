# Wasteland Boiler — Opus diagnostic export

## Cloud command (simple)

After deploy includes `stories:boiler-diagnose`:

```bash
php artisan stories:boiler-diagnose wasteland
```

Uploads:

`exports/wasteland-boiler-opus-diagnostic.json`

Download from Cloud **File explorer** → `exports/`.

### Options

```bash
# default: Storage disk "public" (Cloud File explorer)
php artisan stories:boiler-diagnose wasteland

# write to a local path instead
php artisan stories:boiler-diagnose wasteland --path=/tmp
```

### If jobs are pending but not running

On **Worker Cluster**:

```bash
php artisan queue:work database --queue=adaptation --tries=2 --timeout=900
```

Release reserved leases:

```bash
php artisan tinker --execute='echo DB::table("jobs")->where("queue","adaptation")->whereNotNull("reserved_at")->update(["reserved_at"=>null,"available_at"=>time(),"attempts"=>0]);'
```
