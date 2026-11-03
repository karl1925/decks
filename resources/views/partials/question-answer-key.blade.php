@php
    $keyType = $question?->type ?? ($type ?? 'single_choice');
    $storedKey = $question?->correct_answer;
    $keyText = $keyType === 'matching' && is_array($storedKey)
        ? collect($storedKey)->map(fn ($right, $left) => $left.' | '.$right)->implode("\n")
        : (is_array($storedKey) ? implode("\n", $storedKey) : (string) ($storedKey ?? ''));
    $keyText = old('correct_answer_text', $keyText);
    $keyHint = match($keyType) {
        'single_choice', 'true_false' => 'Enter the exact correct choice.',
        'multiple_choice' => 'Enter every correct choice, one per line.',
        'short_answer' => 'Enter accepted answers, one per line.',
        'ordering' => 'Enter every item in the correct order, one per line.',
        'matching' => 'Enter every correct pair as “left | right”, one per line.',
        default => 'Enter the expected answer.',
    };
@endphp
<label class="correct-answer-field">Correct answer key <small class="correct-answer-hint">{{ $keyHint }}</small><textarea name="correct_answer_text" rows="3" required placeholder="Enter the correct answer…">{{ $keyText }}</textarea>@error('correct_answer_text')<small class="field-error">{{ $message }}</small>@enderror</label>
