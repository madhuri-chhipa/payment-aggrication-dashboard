<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class BankStatementExport implements FromCollection, WithHeadings
{
  protected $transactions;
  protected $startDate;
  protected $endDate;

  public function __construct(array $transactions, string $startDate, string $endDate)
  {
    $this->transactions = $transactions;
    $this->startDate = $startDate;
    $this->endDate = $endDate;
  }

  public function collection()
  {
    return new Collection(array_map(function ($txn, $index) {
      return [
        '#'                  => $index + 1,
        'Tran ID'            => $txn['tranId'] ?? '',
        'Value Date'         => !empty($txn['tranValueDate']) ? Carbon::createFromFormat('Ymd', $txn['tranValueDate'])->format('d-m-Y') : '',
        'Tran Date'          => !empty($txn['tranDate']) ? Carbon::createFromFormat('Ymd', $txn['tranDate'])->format('d-m-Y') : '',
        'Post Date'          => !empty($txn['tranPostDate']) ? Carbon::createFromFormat('YmdHis', $txn['tranPostDate'])->format('d-m-Y h:i:s A') : '',
        'Particulars'        => $txn['tranParticulars'] ?? '',
        'Amount'             => $txn['tranAmount'] ?? '',
        'Dr/Cr'              => $txn['drCRIndicator'] ?? '',
        'Type'               => $txn['tranType'] ?? '',
        'Sub Type'           => $txn['tranSubType'] ?? '',
        'Balance After Tran' => $txn['balAfterTran'] ?? '',
        'Instrument No'      => $txn['instrumentNumber'] ?? '',
        'Serial No'          => $txn['serialNo'] ?? '',
        'Last Tran'          => $txn['isLastTran'] ?? '',
      ];
    }, $this->transactions, array_keys($this->transactions)));
  }

  public function headings(): array
  {
    return [
      '#',
      'Tran ID',
      'Value Date',
      'Tran Date',
      'Post Date',
      'Particulars',
      'Amount',
      'Dr/Cr',
      'Type',
      'Sub Type',
      'Balance After Tran',
      'Instrument No',
      'Serial No',
      'Last Tran',
    ];
  }
}
