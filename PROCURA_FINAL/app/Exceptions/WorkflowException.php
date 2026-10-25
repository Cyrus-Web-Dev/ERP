<?php

namespace App\Exceptions;

use RuntimeException;

/** Aturan alur pengadaan dilanggar (mis. PO dibuat sebelum dana cair). Pesannya aman ditampilkan ke pengguna / klien API. */
class WorkflowException extends RuntimeException
{
}
