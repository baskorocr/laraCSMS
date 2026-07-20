<?php

namespace App\Services\Ocpp;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class DiagnosticsFtpDownloader
{
    public function download(object $request): ?string
    {
        $localPath = $this->localPathFor($request);
        if (is_file($localPath)) {
            return $localPath;
        }

        $remoteDir = $this->resolveRemoteDir($request);
        $fileName = $this->resolveFileName($request, $remoteDir);

        if ($fileName === null) {
            Log::warning('Diagnostics FTP: remote folder empty or missing', [
                'request_id' => $request->id,
                'remote_dir' => $remoteDir,
            ]);

            return null;
        }

        $localDir = dirname($localPath);
        if (! is_dir($localDir) && ! mkdir($localDir, 0755, true) && ! is_dir($localDir)) {
            throw new RuntimeException('Gagal membuat folder penyimpanan diagnostics.');
        }

        $targetLocalPath = $this->localPathFor($request, $fileName);
        $remoteUrl = $this->buildRemoteUrl($remoteDir.'/'.$fileName);

        if (! $this->curlDownload($remoteUrl, $targetLocalPath)) {
            Log::warning('Diagnostics FTP: download failed', [
                'request_id' => $request->id,
                'remote_url' => $this->maskUrl($remoteUrl),
            ]);

            return null;
        }

        if (empty($request->file_name)) {
            DB::table('diagnostics_requests')
                ->where('id', $request->id)
                ->update([
                    'file_name' => $fileName,
                    'updated_at' => now(),
                ]);
        }

        Log::info('Diagnostics FTP: file downloaded', [
            'request_id' => $request->id,
            'file_name' => $fileName,
            'local_path' => $targetLocalPath,
        ]);

        return is_file($targetLocalPath) ? $targetLocalPath : null;
    }

    public function localPathFor(object $request, ?string $fileName = null): string
    {
        $name = $fileName ?? $request->file_name ?? 'diagnostics.zip';

        return storage_path('app/diagnostics/'.$request->id.'_'.$name);
    }

    private function resolveRemoteDir(object $request): string
    {
        return str_replace(
            ['{charge_point_code}', '{message_id}'],
            [(string) $request->charge_point_code, (string) $request->message_id],
            (string) config('ocpp.diagnostics.ftp.remote_path')
        );
    }

    private function resolveFileName(object $request, string $remoteDir): ?string
    {
        if (! empty($request->file_name)) {
            return (string) $request->file_name;
        }

        $entries = $this->curlList($this->buildRemoteUrl($remoteDir.'/'));
        $files = array_values(array_filter(
            $entries,
            static fn (array $entry): bool => $entry['type'] === 'file'
        ));

        if ($files === []) {
            return null;
        }

        foreach ($files as $file) {
            if (str_ends_with(strtolower($file['name']), '.zip')) {
                return $file['name'];
            }
        }

        return $files[0]['name'];
    }

    /**
     * @return array<int, array{name: string, type: 'file'|'dir'}>
     */
    private function curlList(string $url): array
    {
        $output = $this->curlExec($url, null);

        if ($output === null || trim($output) === '') {
            return [];
        }

        $entries = [];
        foreach (preg_split('/\r\n|\n|\r/', trim($output)) ?: [] as $line) {
            $parsed = $this->parseListLine($line);
            if ($parsed !== null) {
                $entries[] = $parsed;
            }
        }

        return $entries;
    }

    /**
     * @return array{name: string, type: 'file'|'dir'}|null
     */
    private function parseListLine(string $line): ?array
    {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, 'total ')) {
            return null;
        }

        // Unix LIST format: drwx------ 3 1000 1000 4096 May 07 07:29 dirname
        if (preg_match('/^[dl-][rwx-]{9}\s+\d+\s+\S+\s+\S+\s+\d+\s+\S+\s+\d+\s+[\d:]+\s+(.+)$/u', $line, $matches)) {
            $name = trim($matches[1]);
            if ($name === '.' || $name === '..') {
                return null;
            }

            return [
                'name' => $name,
                'type' => str_starts_with($line, 'd') ? 'dir' : 'file',
            ];
        }

        return null;
    }

    private function curlDownload(string $url, string $localPath): bool
    {
        return $this->curlExec($url, $localPath) !== null && is_file($localPath) && filesize($localPath) > 0;
    }

    private function curlExec(string $url, ?string $localPath): ?string
    {
        $config = config('ocpp.diagnostics.ftp');
        $ch = curl_init($url);

        if ($ch === false) {
            return null;
        }

        curl_setopt_array($ch, [
            CURLOPT_USERPWD => $config['username'].':'.$config['password'],
            CURLOPT_FTP_USE_EPSV => true,
            CURLOPT_RETURNTRANSFER => $localPath === null,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 120,
        ]);

        if ($localPath !== null) {
            $fp = fopen($localPath, 'wb');
            if ($fp === false) {
                curl_close($ch);

                return null;
            }
            curl_setopt($ch, CURLOPT_FILE, $fp);
        }

        $result = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if (isset($fp)) {
            fclose($fp);
        }

        if ($result === false) {
            Log::warning('Diagnostics FTP curl error', ['error' => $error, 'url' => $this->maskUrl($url)]);

            return null;
        }

        if ($localPath === null) {
            return is_string($result) ? $result : null;
        }

        return ($httpCode === 0 || ($httpCode >= 200 && $httpCode < 400)) ? '' : null;
    }

    private function buildRemoteUrl(string $path): string
    {
        $config = config('ocpp.diagnostics.ftp');
        $path = '/'.ltrim(str_replace('\\', '/', $path), '/');

        return sprintf(
            'ftp://%s:%d%s',
            $config['host'],
            $config['port'],
            $path
        );
    }

    private function maskUrl(string $url): string
    {
        return (string) preg_replace('/(ftp:\/\/)([^:]+):([^@]+)@/i', '$1***:***@', $url);
    }
}
