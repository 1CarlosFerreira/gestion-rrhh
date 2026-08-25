<?php

namespace App\Services\Documentos;

use App\Models\HorasExtraInformeTecnico;
use App\Models\Tramite;
use App\Support\Documentos\DuracionMinutos;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use RuntimeException;

class GeneradorInformeTecnicoXlsx
{
    public function generate(string $templatePath, Tramite $tramite, HorasExtraInformeTecnico $report): string
    {
        if (! is_file($templatePath)) {
            throw new RuntimeException('La plantilla institucional no está disponible.');
        }

        $spreadsheet = IOFactory::load($templatePath);
        $this->keepBaseSheet($spreadsheet);
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Informe Técnico');
        $participants = $tramite->horasExtra->funcionarios->values();
        $extraRows = max(0, $participants->count() - 10);
        if ($extraRows > 0) {
            $sheet->insertNewRowBefore(23, $extraRows);
            for ($row = 23; $row < 23 + $extraRows; $row++) {
                $sheet->duplicateStyle($sheet->getStyle('A22:I22'), 'A'.$row.':I'.$row);
                $sheet->getRowDimension($row)->setRowHeight($sheet->getRowDimension(22)->getRowHeight());
                $sheet->mergeCells('B'.$row.':G'.$row);
            }
        }

        $lastParticipantRow = 12 + $participants->count();
        $templateLastRow = 22 + $extraRows;
        for ($row = 13; $row <= $templateLastRow; $row++) {
            foreach (['A', 'B', 'H', 'I'] as $column) {
                $sheet->setCellValue($column.$row, null);
            }
        }
        foreach ($participants as $index => $participant) {
            $row = 13 + $index;
            $sheet->setCellValueExplicit('A'.$row, ($index + 1).'.-', DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('B'.$row, $participant->persona->nombre_completo, DataType::TYPE_STRING);
            $sheet->setCellValue('H'.$row, DuracionMinutos::asExcelTime($participant->daytime_minutes));
            $sheet->setCellValue('I'.$row, DuracionMinutos::asExcelTime($participant->night_festive_minutes));
            $sheet->getStyle('H'.$row.':I'.$row)->getNumberFormat()->setFormatCode('[hh]:mm');
        }

        $offset = $extraRows;
        $sheet->setCellValueExplicit('B7', $tramite->unidadServicio->nombre, DataType::TYPE_STRING);
        $totalCell = 'E'.(24 + $offset);
        $sheet->setCellValue($totalCell, '=SUM(H13:I'.$lastParticipantRow.')');
        $sheet->getStyle($totalCell)->getNumberFormat()->setFormatCode('[hh]:mm');
        $sheet->setCellValueExplicit('E'.(26 + $offset), $report->horario_diurno ? 'X' : '', DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('G'.(26 + $offset), $report->horario_festivo ? 'X' : '', DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('E'.(28 + $offset), $report->retribucion_tiempo ? 'X' : '', DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('G'.(28 + $offset), $report->retribucion_dinero ? 'X' : '', DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('A'.(31 + $offset), 'Desde el: '.$tramite->horasExtra->period_start->format('d.m.Y'), DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('E'.(31 + $offset), 'Hasta el: '.$tramite->horasExtra->period_end->format('d.m.Y'), DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('A'.(34 + $offset), $report->justificacion_tecnica, DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('A'.(37 + $offset), $report->medidas_control, DataType::TYPE_STRING);
        $sheet->setCellValue('B'.(42 + $offset), ExcelDate::PHPToExcel(now()));
        $sheet->getStyle('B'.(42 + $offset))->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_DATE_DDMMYYYY);
        $sheet->getPageSetup()->setPrintArea('A1:I'.(43 + $offset));

        $temporaryPath = tempnam(sys_get_temp_dir(), 'he-report-');
        if ($temporaryPath === false) {
            throw new RuntimeException('No fue posible crear el archivo temporal.');
        }
        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($temporaryPath);
        $spreadsheet->disconnectWorksheets();

        return $temporaryPath;
    }

    private function keepBaseSheet(Spreadsheet $spreadsheet): void
    {
        while ($spreadsheet->getSheetCount() > 1) {
            $spreadsheet->removeSheetByIndex(1);
        }
        $spreadsheet->setActiveSheetIndex(0);
    }
}
