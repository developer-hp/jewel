<x-row-actions :edit-url="route('sales-persons.edit', $person)" :delete-url="route('sales-persons.destroy', $person)"
    edit-permission="sales_person.edit" delete-permission="sales_person.delete"
    :confirm="'Delete sales person ' . $person->name . '?'" />
