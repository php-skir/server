<?php

declare(strict_types=1);

namespace Skir\Server\Studio;

use Illuminate\Support\Str;
use Skir\Runtime\Field;
use Skir\Runtime\Type;
use Skir\Runtime\TypeKind;
use Skir\Runtime\Variant;

/**
 * Describes a runtime type in the JSON format of Skir's type descriptors: a type signature plus the
 * definition of every struct and enum it refers to.
 *
 * Runtime types carry no record names, so names are derived: the root is named by the caller, and a nested
 * record is named after the field or variant that holds it. Records with the same shape share one definition.
 */
final class TypeDescriptor
{
    private const string MODULE = 'skir';

    /** @var array<string, string> */
    private array $recordIdsByShape = [];

    /** @var array<string, array<string, mixed>> */
    private array $records = [];

    /**
     * @return array{
     *     type: array<string, mixed>,
     *     records: list<array<string, mixed>>
     * }
     */
    public function describe(Type $type, string $name): array
    {
        $this->recordIdsByShape = [];
        $this->records = [];

        return [
            'type' => $this->signature($type, $name),
            'records' => array_values($this->records),
        ];
    }

    /** @return array<string, mixed> */
    private function signature(Type $type, string $name): array
    {
        return match ($type->kind) {
            TypeKind::Optional => [
                'kind' => 'optional',
                'value' => $this->signature($this->itemType($type), $name),
            ],
            TypeKind::Array => [
                'kind' => 'array',
                'value' => ['item' => $this->signature($this->itemType($type), Str::singular($name))],
            ],
            TypeKind::Struct, TypeKind::Enum => [
                'kind' => 'record',
                'value' => $this->recordId($type, $name),
            ],
            default => [
                'kind' => 'primitive',
                'value' => $type->kind->value,
            ],
        };
    }

    private function recordId(Type $type, string $name): string
    {
        $shape = serialize($type);

        if (isset($this->recordIdsByShape[$shape])) {
            return $this->recordIdsByShape[$shape];
        }

        $recordId = $this->uniqueRecordId($name);

        // Registered before its fields are described, so the definitions come out parent first.
        $this->recordIdsByShape[$shape] = $recordId;
        $this->records[$recordId] = [];
        $this->records[$recordId] = $type->kind === TypeKind::Struct
            ? $this->structDefinition($type, $recordId)
            : $this->enumDefinition($type, $recordId);

        return $recordId;
    }

    private function uniqueRecordId(string $name): string
    {
        $studlyName = Str::studly($name);
        $recordId = self::MODULE.":{$studlyName}";
        $suffix = 2;

        while (isset($this->records[$recordId])) {
            $recordId = self::MODULE.":{$studlyName}{$suffix}";
            $suffix++;
        }

        return $recordId;
    }

    /** @return array<string, mixed> */
    private function structDefinition(Type $type, string $recordId): array
    {
        $fields = [];
        $removedNumbers = [];

        foreach ($type->fields as $field) {
            if ($field->removed) {
                $removedNumbers[] = $field->number;

                continue;
            }

            $fields[] = $this->fieldDefinition($field);
        }

        $definition = [
            'kind' => 'struct',
            'id' => $recordId,
            'fields' => $fields,
        ];

        if ($removedNumbers !== []) {
            $definition['removed_numbers'] = $removedNumbers;
        }

        return $definition;
    }

    /** @return array<string, mixed> */
    private function fieldDefinition(Field $field): array
    {
        $name = (string) $field->name;

        return [
            'name' => $name,
            'number' => $field->number,
            'type' => $this->signature($field->type ?? Type::bool(), $name),
        ];
    }

    /** @return array<string, mixed> */
    private function enumDefinition(Type $type, string $recordId): array
    {
        $variants = [];

        foreach ($type->variants as $variant) {
            // Number 0 is the implicit UNKNOWN variant, which type descriptors leave out.
            if ($variant->number === 0) {
                continue;
            }

            $variants[] = $this->variantDefinition($variant);
        }

        return [
            'kind' => 'enum',
            'id' => $recordId,
            'variants' => $variants,
        ];
    }

    /** @return array<string, mixed> */
    private function variantDefinition(Variant $variant): array
    {
        $definition = [
            'name' => $variant->name,
            'number' => $variant->number,
        ];

        if ($variant->payloadType !== null) {
            $definition['type'] = $this->signature($variant->payloadType, $variant->name);
        }

        return $definition;
    }

    private function itemType(Type $type): Type
    {
        return $type->itemType ?? Type::bool();
    }
}
