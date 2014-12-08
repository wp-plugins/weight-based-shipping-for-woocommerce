<?php
    class WBS_Shipping_Rate_Override
    {
        private $class;
        private $fee;
        private $rate;
        private $weightStep;

        public function __construct($class, $fee, $rate, $weightStep)
        {
            $this->setClass($class);
            $this->setFee($fee);
            $this->setRate($rate);
            $this->setWeightStep($weightStep);
        }

        public function getClass()
        {
            return $this->class;
        }

        private function setClass($class)
        {
            if (empty($class)) {
                throw new InvalidArgumentException("Please provide class for shipping class override");
            }

            $this->class = $class;
        }

        public function getFee()
        {
            return $this->fee;
        }

        public function getRate()
        {
            return $this->rate;
        }

        public function getWeightStep()
        {
            return $this->weightStep;
        }

        private function setFee($fee)
        {
            if (empty($fee)) {
                $fee = 0;
            }

            $this->fee = $fee;
        }

        private function setRate($rate)
        {
            if (empty($rate)) {
                $rate = 0;
            }

            $this->rate = $rate;
        }

        private function setWeightStep($weightStep)
        {
            if (empty($weightStep)) {
                $weightStep = null;
            }

            $this->weightStep = $weightStep;
        }
    }
?>