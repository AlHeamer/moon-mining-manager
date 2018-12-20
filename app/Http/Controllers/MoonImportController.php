<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Moon;
use App\Region;
use App\SolarSystem;
use App\Miner;
use App\Type;
use App\TaxRate;
use App\Jobs\UpdateReprocessedMaterials;
use App\Jobs\UpdateMaterialValues;
use Illuminate\Support\Facades\Log;

class MoonImportController extends Controller
{

    protected $total_ore_volume = 14000000; // 14m m3 represents a thirty day mining cycle, approximately

    public function index()
    {
        $moons = Moon::orderBy('region_id')->orderBy('solar_system_id')->orderBy('planet')->orderBy('moon')->get();
        return view('moons.import', [
            'moons' => $moons,
        ]);
    }

    public function import(Request $request)
    {

        // Convert the dump of spreadsheet data into a structured array.
        $data = [];
        $lines = explode("\n", $request->input('data'));
        foreach ($lines as $line)
        {
            $data[] = explode("\t", $line);
        }

        // Loop through each row and process it into the database.
        foreach ($data as $row)
        {
            $moon = new Moon;
            $moon->region_id = Region::where('regionName', $row[0])->first()->regionID;
            $moon->solar_system_id = SolarSystem::where('solarSystemName', $row[1])->first()->solarSystemID;
            $moon->planet = $row[2];
            $moon->moon = $row[3];
            /*
            if ($row[4])
            {
                $moon->renter_id = Miner::where('name', $row[4])->first()->eve_id;
            }
            */
            $moon->mineral_1_type_id = Type::where('typeName', $row[5])->first()->typeID;
            $moon->mineral_1_percent = str_replace(',', '.', $row[6]);
            $moon->mineral_2_type_id = Type::where('typeName', $row[7])->first()->typeID;
            $moon->mineral_2_percent = str_replace(',', '.', $row[8]);
            if ($row[9])
            {
                $moon->mineral_3_type_id = Type::where('typeName', $row[9])->first()->typeID;
                $moon->mineral_3_percent = str_replace(',', '.', $row[10]);
            }
            if ($row[11])
            {
                $moon->mineral_4_type_id = Type::where('typeName', $row[11])->first()->typeID;
                $moon->mineral_4_percent = str_replace(',', '.', $row[12]);
            }
            $moon->monthly_rental_fee = 0;
            $moon->previous_monthly_rental_fee = 0;
            $moon->save();
        }

        // Redirect back to the list.
        return redirect('/moonadmin');

    }

    public function importSurveyData(Request $request)
    {
        $moon = null;
        $num = 0;
        foreach (explode("\n", $request->input('data')) as $row) {
            $cols = explode("\t", $row);

            // new moon?
            $matches = [];
            if (preg_match('/([A-Z0-9-]{6}) ([XVI]{1,4}) - Moon ([0-9]{1,2})/', trim($cols[0]), $matches)) {

                // save previous moon
                if ($moon instanceof Moon) {
                    $moon->save();
                }

                $num = 0;
                $moon = new Moon;
                $moon->planet = $this->romanNumberToInteger($matches[2]);
                $moon->moon = $matches[3]; // moon number
                $moon->monthly_rental_fee = 0;
                $moon->previous_monthly_rental_fee = 0;

                continue;
            }

            // skip the headline
            if ($moon === null) {
                continue;
            }

            // moon data
            $num ++;
            $moon->solar_system_id = trim($cols[4]);
            $moon->region_id = SolarSystem::where('solarSystemID', trim($cols[4]))->first()->regionID;
            $moon->{'mineral_'.$num.'_type_id'} = trim($cols[3]);
            $moon->{'mineral_'.$num.'_percent'} = trim($cols[2]) * 100;
        }

        // save last moon
        if ($moon instanceof Moon) {
            $moon->save();
        }

        // Redirect back to the list.
        return redirect('/moonadmin');
    }

    /**
     * Calculate the monthly rental fee for every moon, based on its mineral composition.
     */
    public function calculate()
    {

        // Grab all of the moon records and loop through them.
        $moons = Moon::all();
        foreach ($moons as $moon)
        {
            // Set the monthly rental value to zero.
            $monthly_rental_fee = 0;

            $monthly_rental_fee += $this->calculateOreTaxValue($moon->mineral_1_type_id, $moon->mineral_1_percent, $moon->solar_system_id);
            $monthly_rental_fee += $this->calculateOreTaxValue($moon->mineral_2_type_id, $moon->mineral_2_percent, $moon->solar_system_id);
            if ($moon->mineral_3_type_id)
            {
                $monthly_rental_fee += $this->calculateOreTaxValue($moon->mineral_3_type_id, $moon->mineral_3_percent, $moon->solar_system_id);
            }
            if ($moon->mineral_4_type_id)
            {
                $monthly_rental_fee += $this->calculateOreTaxValue($moon->mineral_4_type_id, $moon->mineral_4_percent, $moon->solar_system_id);
            }

            // Save the updated rental fee.
            $moon->monthly_rental_fee = $monthly_rental_fee;
            $moon->save();
        }

        // Redirect back to the moon list.
        return redirect('/moonadmin');

    }

    private function calculateOreTaxValue($type_id, $percent, $solar_system_id)
    {
        // Retrieve the value of the mineral from the taxes table.
        $tax_rate = TaxRate::where('type_id', $type_id)->first();

        // If we don't have a stored tax rate for this ore type, queue a job to calculate it.
        if (isset($tax_rate))
        {
            // Grab the stored value of this ore.
            $ore_value = $tax_rate->value;

            // Calculate what volume of the total ore will be this type.
            $ore_volume = $this->total_ore_volume * $percent / 100;

            // Based on the volume of the ore type, how many units does that volume represent.
            $type = Type::find($type_id);
            $units = $ore_volume / $type->volume;

            // Calculate the tax rate to apply (premium applied in the Impass pocket).
            $tax_rate = (SolarSystem::find($solar_system_id)->constellationID == 20000383) ? 10 : 7;

            // For non-moon ores, apply a 50% discount.
            $discount = (in_array($type->groupID, [1884, 1920, 1921, 1922, 1923])) ? 1 : 0.5;

            // Calculate the tax value to be charged for the volume of this ore that can be mined.
            return $ore_value * $units * $tax_rate / 100 * $discount;
        }
        else
        {
            // Add a new record for this unknown ore type.
            $tax_rate = new TaxRate;
            $tax_rate->type_id = $type_id;
            $tax_rate->check_materials = 1;
            $tax_rate->value = 0;
            $tax_rate->tax_rate = 7;
            $tax_rate->updated_by = 0;
            $tax_rate->save();
            Log::info('MoonImportController: unknown ore ' . $type_id . ' found, new tax rate record created');
            // Queue the jobs to update the ore values rather than waiting for the next scheduled job.
            UpdateReprocessedMaterials::dispatch();
            UpdateMaterialValues::dispatch();
        }
    }

    private function romanNumberToInteger($roman)
    {
        $romans = array(
            'M' => 1000,
            'CM' => 900,
            'D' => 500,
            'CD' => 400,
            'C' => 100,
            'XC' => 90,
            'L' => 50,
            'XL' => 40,
            'X' => 10,
            'IX' => 9,
            'V' => 5,
            'IV' => 4,
            'I' => 1,
        );

        $result = 0;

        foreach ($romans as $key => $value) {
            while (strpos($roman, $key) === 0) {
                $result += $value;
                $roman = substr($roman, strlen($key));
            }
        }

        return $result;
    }
}
