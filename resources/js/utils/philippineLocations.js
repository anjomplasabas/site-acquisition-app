// Curated portfolio demo locations, not the full PSGC registry.
// A site outside this list is still supported via each field's "Other (type manually)" option
// (see CityProvinceFields.vue), so this list only needs to cover the common case.
export const PH_LOCATIONS = [
    {
        province: 'Metro Manila',
        cities: [
            'Caloocan', 'Las Piñas', 'Makati', 'Malabon', 'Mandaluyong', 'Manila',
            'Marikina', 'Muntinlupa', 'Navotas', 'Parañaque', 'Pasay', 'Pasig',
            'Pateros', 'Quezon City', 'San Juan', 'Taguig', 'Valenzuela',
        ],
    },
    {
        province: 'Cavite',
        cities: ['Bacoor', 'Cavite City', 'Dasmariñas', 'General Trias', 'Imus', 'Tagaytay', 'Trece Martires'],
    },
    {
        province: 'Laguna',
        cities: ['Biñan', 'Cabuyao', 'Calamba', 'Los Baños', 'San Pedro', 'Santa Rosa'],
    },
    {
        province: 'Rizal',
        cities: ['Angono', 'Antipolo', 'Binangonan', 'Cainta', 'Taytay'],
    },
    {
        province: 'Bulacan',
        cities: ['Bocaue', 'Malolos', 'Marilao', 'Meycauayan', 'San Jose del Monte'],
    },
    {
        province: 'Pampanga',
        cities: ['Angeles City', 'Mabalacat', 'San Fernando'],
    },
    {
        province: 'Batangas',
        cities: ['Batangas City', 'Lipa', 'Tanauan'],
    },
    {
        province: 'Cebu',
        cities: ['Cebu City', 'Lapu-Lapu City', 'Mandaue City', 'Talisay City'],
    },
    {
        province: 'Davao del Sur',
        cities: ['Davao City', 'Digos'],
    },
]

export const PH_PROVINCES = PH_LOCATIONS.map(entry => entry.province)

export function citiesForProvince(province) {
    return PH_LOCATIONS.find(entry => entry.province === province)?.cities ?? []
}
