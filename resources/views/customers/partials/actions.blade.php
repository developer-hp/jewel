<x-row-actions :edit-url="route('customers.edit', $customer)" :delete-url="route('customers.destroy', $customer)"
    edit-permission="customer.edit" delete-permission="customer.delete"
    :confirm="'Delete customer ' . $customer->name . '?'" />
