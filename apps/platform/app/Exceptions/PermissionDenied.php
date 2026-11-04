<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * 423 when the parent PIN on the wall would help — the wall then asks for it
 * and retries. 403 when nothing helps, e.g. a child signed in on the phone.
 */
final class PermissionDenied extends RuntimeException
{
    public function __construct(private readonly bool $pinHelps)
    {
        parent::__construct($pinHelps ? __('This needs the parent PIN.') : __('You do not have permission for this.'));
    }

    public function render(): JsonResponse
    {
        return $this->pinHelps
            ? response()->json(['message' => $this->getMessage(), 'pin_required' => true], 423)
            : response()->json(['message' => $this->getMessage()], 403);
    }
}
