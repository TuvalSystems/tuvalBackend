<?php

namespace App\Exports\Cobranzas\CobrosFacturas;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;


class ClientAccountStatementDetailExport implements 
    FromCollection,
    WithHeadings,
    WithMapping,
    WithColumnFormatting,
    WithColumnWidths,
    WithStyles,
    WithTitle
{
    public function __construct(
        private readonly Collection $detail
    ) {
    }

    public function collection(): Collection
    {
        return $this->detail;
    }

    public function headings(): array
    {
        return [
            'Tipo Doc.',
            'Nro. documento',
            'Fecha documento',
            'Fecha vencimiento',
            'Condición de pago',
            'Valor documento',
            'Abono',
            'Saldo pendiente',
        ];
    }

    public function map($item): array
    {
        return [
            $item->tipo ?? '',
            $item->documento ?? '',
            $item->fechaEmision ?? '',
            $item->fechaVencimiento ?? '',
            $item->condicionPago ?? '',
            (float) ($item->montoOriginal ?? 0),
            (float) ($item->abono ?? 0),
            (float) ($item->saldoPendiente ?? 0),
        ];
    }

    public function columnFormats(): array
    {
        return [
            'F' => NumberFormat::FORMAT_CURRENCY_USD,
            'G' => NumberFormat::FORMAT_CURRENCY_USD,
            'H' => NumberFormat::FORMAT_CURRENCY_USD,
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 14,
            'B' => 24,
            'C' => 18,
            'D' => 18,
            'E' => 20,
            'F' => 18,
            'G' => 16,
            'H' => 18,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->freezePane('A2');

        $sheet->setAutoFilter(
            'A1:H' . max($sheet->getHighestRow(), 1)
        );

        $sheet->getStyle(
            'A1:H' . max($sheet->getHighestRow(), 1)
        )->getAlignment()->setVertical(
            Alignment::VERTICAL_CENTER
        );

        $sheet->getStyle(
            'I2:H' . max($sheet->getHighestRow(), 2)
        )->getAlignment()->setWrapText(true);

        return [
            1 => [
                'font' => [
                    'bold' => true,
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }

    public function title(): string
    {
        return 'Estado de cuenta';
    }
}