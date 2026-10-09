import { defineCollection, z } from 'astro:content';
export const categories = ['Writing', 'Articles', 'Research', 'Editing & Quality', 'Data & Technical'] as const;
const projects = defineCollection({
  type: 'content',
  schema: z.object({
    title: z.string(),
    date: z.coerce.date(),
    category: z.enum(categories),
    excerpt: z.string(),
    cover: z.string().optional(),
    coverAlt: z.string().default(''),
    video: z.string().optional(),
    featured: z.boolean().default(false),
    address: z.string().optional(),
  }),
});
const services = defineCollection({
  type: 'content',
  schema: z.object({
    title: z.string(),
    order: z.number().default(100),
    excerpt: z.string(),
    image: z.string(),
    imageAlt: z.string().default(''),
    address: z.string().optional(),
  }),
});
export const collections = { projects, services };
