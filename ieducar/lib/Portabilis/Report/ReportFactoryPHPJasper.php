<?php

use PHPJasper\PHPJasper;

class Portabilis_Report_ReportFactoryPHPJasper extends Portabilis_Report_ReportFactory
{
    /**
     * Define as configurações dos relatórios.
     *
     * @param object $config
     * @return void
     */
    public function setSettings($config)
    {
        $this->settings['db'] = $config->app->database;
        $this->settings['logo_file_name'] = $config->report->logo_file_name;
    }

    /**
     * Retorna o diretório dos relatórios.
     *
     * @return string
     */
    public function getReportsPath()
    {
        return config('legacy.report.source_path');
    }

    /**
     * Retorna o arquivo da logo utilizada nos relatórios.
     *
     * @return string
     *
     * @throws CoreExt_Exception
     * @throws Exception
     */
    public function logoPath()
    {
        $logo = $this->settings['logo_file_name'];

        if (!$logo) {
            throw new Exception('No report.logo_file_name defined in configurations!');
        }

        if (filter_var($logo, FILTER_VALIDATE_URL)) {
            $tmpFile = sys_get_temp_dir() . '/logo_' . hash('sha256', $logo) . '.png';

            if (!file_exists($tmpFile)) {
                $imageData = file_get_contents($logo);
                if ($imageData === false) {
                    throw new Exception("Erro ao baixar logo da URL: $logo");
                }
                file_put_contents($tmpFile, $imageData);
            }

            return $tmpFile;
        }

        $rootPath = dirname(dirname(dirname(dirname(__FILE__))));
        $filePath = $rootPath . "/modules/Reports/ReportLogos/{$logo}";

        if (!file_exists($filePath)) {
            throw new CoreExt_Exception("Report logo '{$this->settings['logo_file_name']}' not found in path '$filePath'");
        }

        return $filePath;
    }

    /**
     * Renderiza o relatório.
     *
     * @param Portabilis_Report_ReportCore $report
     * @param array                        $options
     * @return void
     *
     * @throws Exception
     */
    public function dumps($report, $options = [])
    {
        $options = self::mergeOptions($options, [
            'add_logo_arg' => true,
        ]);

        if ($options['add_logo_arg']) {
            $report->addArg('logo', $this->logoPath());
        }

        $dataFile = $this->getReportsPath() . time() . '-' . mt_rand();
        $outputFile = $this->getReportsPath() . time() . '-' . mt_rand();
        $filename = $this->getReportsPath() . $report->templateName();
        $jasperFile = $filename . '.jasper';
        $jrxmlFile = $filename . '.jrxml';

        foreach ($report->args as $key => $value) {
            if (is_bool($value)) {
                $report->args[$key] = ($value ? 'true' : 'false');
            }
        }

        $jasper = new PHPJasper;

        // Compila o arquivo .jrxml caso o arquivo .jasper não exista.

        if (file_exists($jasperFile) === false) {
            $jasper->compile($jrxmlFile, $filename)->execute();
        }

        // Com o intuito de manter a compatibilidade até finalizar a migração
        // de todos os relatórios será utilizado o método useJson() para
        // informar qual tipo de data source será utilizado.

        $resourceDir = base_path();

        if ($report->useJson()) {
            $data = $report->getJsonData();
            $data = $report->modify($data);
            $json = json_encode($data);

            file_put_contents($dataFile, $json);

            $report->addArg('source', $dataFile);

            $dbConnection = [
                'driver' => 'json',
                'data_file' => $dataFile,
            ];
            $jsonQuery = $report->getJsonQuery();
            if ($jsonQuery !== null && $jsonQuery !== '') {
                $dbConnection['json_query'] = $jsonQuery;
            }

            $jasper->process($jasperFile, $outputFile, [
                'format' => ['pdf'],
                'params' => $report->args,
                'resources' => $resourceDir,
                'db_connection' => $dbConnection,
            ])->execute();

            unlink($dataFile);
        } else {
            $jasper->process($jasperFile, $outputFile, [
                'format' => ['pdf'],
                'params' => $report->args,
                'resources' => $resourceDir,
                'db_connection' => $this->jasperPostgresDbConnection(),
            ])->execute();
        }

        $outputFile .= '.pdf';

        $result = file_exists($outputFile)
            ? file_get_contents($outputFile)
            : null;

        $this->destroyPDF($outputFile);

        return $result;
    }

    /**
     * Deleta o PDF gerado.
     *
     * @param string $file
     * @return void
     */
    public function destroyPDF($file)
    {
        if (file_exists($file)) {
            unlink($file);
        }
    }

    /** @return array<string, mixed> */
    private function jasperPostgresDbConnection(): array
    {
        $db = $this->settings['db'];
        $conn = ['driver' => 'postgres'];

        if (!empty($db->username)) {
            $conn['username'] = $db->username;
        }
        if (!empty($db->password)) {
            $conn['password'] = $db->password;
        }
        if (!empty($db->hostname)) {
            $conn['host'] = $db->hostname;
        }
        if (!empty($db->dbname)) {
            $conn['database'] = $db->dbname;
        }
        if (!empty($db->port)) {
            $conn['port'] = $db->port;
        }

        return $conn;
    }
}
