<?php
namespace Mita\UranusHttpServer\Services;

use Psr\Http\Message\ServerRequestInterface;
use Respect\Validation\Exceptions\NestedValidationException;
use SlimSession\Helper as Session;

class ValidatorService
{
    private $session;

    public function __construct(Session $session) 
    {
        $this->session = $session;
    }

    /** @var array Validations errors */
    protected $errors = [];

    /**
     * Validate request params based on provided rules and fields
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param array                                    $rules
     *
     * @return static
     */
    public function validate(ServerRequestInterface $request, array $rules)
    {
        $this->clearErrors();
        $body = $request->getParsedBody();

        /** @var \Respect\Validation\Validator $rule */
        foreach ($rules as $field => $rule) {
            try {
                $rule->setName($field)->assert($this->getValue($body, $field));
            } catch (NestedValidationException $e) {
                $this->setError($field, $e->getFullMessage());
            }
        }

        $this->session->set('errors', $this->errors);
        return $this;
    }

    /**
     * Validate an array of values and fields
     *
     * @param array $values
     * @param array $rules
     *
     * @return static
     */
    public function validateArray(array $values, array $rules)
    {
        $this->clearErrors();

        /** @var \Respect\Validation\Validator $rule */
        foreach ($rules as $field => $rule) {
            try {
                $rule->setName($field)->assert($this->getValue($values, $field));
            } catch (NestedValidationException $e) {
                $this->setError($field, $e->getFullMessage());
            }
        }

        $this->session->set('errors', $this->errors);
        return $this;
    }

    /**
     * Clear all validation errors
     */
    public function clearErrors()
    {
        $this->errors = [];
        $this->session->delete('errors');
    }

    /**
     * Check if there is any validation error
     *
     * @return bool
     */
    public function failed()
    {
        return !empty($this->errors);
    }

    /**
     * Return all validations errors if any
     *
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Get the value of the array
     *
     * @param $values
     * @param $field
     *
     * @return string|null
     */
    private function getValue($values, $field)
    {
        return $values[$field] ?? null;
    }

    public function setErrors(array $errors)
    {
        $this->errors = $errors;
    }

    public function setError($field, $message)
    {
        $this->errors[$field] = $message;
    }
}
